<?php

namespace Lunar\ProductImport\Persisting;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lunar\Base\MediaFileName;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImageRefused;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Downloads an image URL of an imported product into its media. It fetches
 * only HTTP and HTTPS URLs whose host resolves only to public addresses,
 * checks every redirect the same way (at most 3), and refuses responses over
 * `images.max_size_kb`, slower than `images.timeout`, or of a type other
 * than JPEG, PNG, GIF, or WebP. A URL the product already has as media
 * (the `source_url` custom property) is not downloaded again. The file is
 * named after the product, like an image uploaded in the admin. A failed
 * image is a warning on its row and never fails the row.
 */
class ImageDownloader
{
    /**
     * Redirects followed at most.
     */
    public const MAX_REDIRECTS = 3;

    /**
     * The accepted image types and their file extensions.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(protected HostResolver $hosts) {}

    /**
     * Download one pending image, or record why it failed.
     */
    public function download(ProductImportImage $image): void
    {
        $product = Product::withTrashed()->find($image->product_id);

        if ($product === null) {
            $this->fail($image, __('lunarpanel.product-import::images.reasons.product_missing'));

            return;
        }

        $existing = $this->existingMedia($product, $image->url);

        if ($existing !== null) {
            $this->linkVariant($image, $existing);
            $image->forceFill(['status' => ImageStatus::Existing, 'media_id' => $existing->id, 'error' => null])->save();

            return;
        }

        try {
            [$content, $extension] = $this->fetch($image->url);

            $fileName = $this->fileName($product, $this->name($image->url).'.'.$extension);
            $productName = MediaFileName::ownerName($product);

            $media = $product->addMediaFromString($content)
                ->usingName(filled($productName) ? Str::limit($productName, 255, '') : pathinfo($fileName, PATHINFO_FILENAME))
                ->usingFileName($fileName)
                ->withCustomProperties([
                    'source_url' => $image->url,
                    'primary' => $this->isPrimary($image, $product),
                ])
                ->toMediaCollection((string) config('lunar.media.collection', 'images'));
        } catch (ImageRefused $exception) {
            $this->fail($image, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($image, __('lunarpanel.product-import::images.reasons.not_saved'));

            return;
        }

        $this->linkVariant($image, $media);
        $image->forceFill(['status' => ImageStatus::Downloaded, 'media_id' => $media->id, 'error' => null])->save();
    }

    /**
     * Fetch an image, following at most three redirects, each checked like
     * the first address.
     *
     * @return array{0: string, 1: string} The content and its file extension.
     *
     * @throws ImageRefused
     */
    public function fetch(string $url): array
    {
        $url = Str::trim($url);

        for ($redirects = 0; ; $redirects++) {
            $address = $this->allowedAddress($url);
            $response = $this->request($url, $address);

            if ($response->redirect()) {
                $location = Str::trim((string) $response->header('Location'));

                if ($location === '') {
                    throw ImageRefused::because('http_status', ['status' => $response->status()]);
                }

                if ($redirects >= self::MAX_REDIRECTS) {
                    throw ImageRefused::because('too_many_redirects', ['count' => self::MAX_REDIRECTS]);
                }

                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            if (! $response->successful()) {
                throw ImageRefused::because('http_status', ['status' => $response->status()]);
            }

            return $this->content($response);
        }
    }

    /**
     * Check the URL and return the public address to connect to. An IP
     * address host is checked as it is; a host name must resolve only to
     * public addresses.
     *
     * @throws ImageRefused
     */
    public function allowedAddress(string $url): string
    {
        if (! Str::isUrl($url, ['http', 'https'])) {
            throw ImageRefused::because('not_web');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = Str::of($host)->ltrim('[')->rtrim(']')->lower()->toString();

        if ($host === '') {
            throw ImageRefused::because('not_web');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->hosts->addresses($host);

        if ($addresses === []) {
            throw ImageRefused::because('unresolved');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw ImageRefused::because('not_allowed');
            }
        }

        return $addresses[0];
    }

    /**
     * Send the request without following redirects, connecting to the
     * checked address so the host cannot resolve elsewhere in between.
     *
     * @throws ImageRefused
     */
    protected function request(string $url, string $address): Response
    {
        $timeout = max(1, (int) config('lunar.product-import.images.timeout', 20));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: (Str::startsWith(Str::lower($url), 'https') ? 443 : 80);
        $options = ['allow_redirects' => false, 'stream' => true];

        if (filter_var($host, FILTER_VALIDATE_IP) === false && defined('CURLOPT_RESOLVE')) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:".(Str::contains($address, ':') ? "[{$address}]" : $address)]];
        }

        try {
            return Http::withOptions($options)
                ->withHeaders(['Accept' => 'image/jpeg, image/png, image/gif, image/webp'])
                ->connectTimeout(min(10, $timeout))
                ->timeout($timeout)
                ->get($url);
        } catch (ConnectionException) {
            throw ImageRefused::because('unreachable');
        }
    }

    /**
     * Read the body up to the size limit and check its type.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ImageRefused
     */
    protected function content(Response $response): array
    {
        $limit = max(1, (int) config('lunar.product-import.images.max_size_kb', 10240)) * 1024;
        $tooLarge = ImageRefused::because('too_large', ['size' => (int) ($limit / 1024)]);

        if ((int) $response->header('Content-Length') > $limit) {
            throw $tooLarge;
        }

        $type = Str::of((string) $response->header('Content-Type'))->before(';')->trim()->lower()->toString();

        if (! isset(self::TYPES[$type])) {
            throw ImageRefused::because('wrong_type');
        }

        $body = $response->toPsrResponse()->getBody();
        $content = '';

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (! $body->eof()) {
            $content .= $body->read(65536);

            if (strlen($content) > $limit) {
                throw $tooLarge;
            }
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if (! is_string($detected) || ! isset(self::TYPES[$detected])) {
            throw ImageRefused::because('wrong_type');
        }

        return [$content, self::TYPES[$detected]];
    }

    /**
     * The product's image that came from the URL earlier, if any.
     */
    protected function existingMedia(Product $product, string $url): ?Media
    {
        return $product->media()
            ->where('collection_name', (string) config('lunar.media.collection', 'images'))
            ->get()
            ->first(fn (Media $media): bool => $media->getCustomProperty('source_url') === $url);
    }

    /**
     * A file name for the product's new image, named after the product like
     * an image uploaded in the admin, with a number suffix when another of
     * the product's images uses it. The product's images share one folder,
     * so two images named after their URLs (`…/1/800/1000.jpg` and
     * `…/2/800/1000.jpg`) would overwrite each other's file.
     */
    protected function fileName(Product $product, string $urlFileName): string
    {
        return MediaFileName::unique(
            MediaFileName::forOwner($urlFileName, $product),
            MediaFileName::usedBy($product->getMorphClass(), $product->getKey()),
        );
    }

    /**
     * Whether the image becomes the product's primary image: the import's
     * first image of a product that has no primary image yet. The product's
     * own images come first, so a variant's image is the primary image only
     * when the file gives the product no image of its own.
     */
    protected function isPrimary(ProductImportImage $image, Product $product): bool
    {
        $first = ProductImportImage::query()
            ->where('product_import_id', $image->product_import_id)
            ->where('product_id', $image->product_id)
            ->min('position');

        return (int) $first === $image->position
            && ! $product->media()->get()->contains(fn (Media $media): bool => $media->getCustomProperty('primary') === true);
    }

    /**
     * Link the image to its variant, as the variant's primary image when it
     * has none.
     */
    protected function linkVariant(ProductImportImage $image, Media $media): void
    {
        if ($image->product_variant_id === null) {
            return;
        }

        $variant = ProductVariant::withTrashed()->find($image->product_variant_id);

        if ($variant === null || $variant->images()->whereKey($media->id)->exists()) {
            return;
        }

        $variant->images()->attach($media->id, [
            'primary' => ! $variant->images()->exists(),
            'position' => (int) $variant->images()->count() + 1,
        ]);
    }

    /**
     * Mark the image as failed and warn on its row, unless the row already
     * has the same warning from an earlier attempt.
     */
    protected function fail(ProductImportImage $image, string $reason): void
    {
        $image->forceFill(['status' => ImageStatus::Failed, 'error' => Str::limit($reason, 500, '')])->save();

        $row = $image->product_import_row_id !== null ? ProductImportRow::query()->find($image->product_import_row_id) : null;

        if ($row === null) {
            return;
        }

        $warning = [
            'level' => 'warning',
            'code' => 'image_failed',
            'field' => $image->product_variant_id !== null ? TargetField::VariantImage->value : TargetField::ProductImages->value,
            'message' => Str::ucfirst(__('lunarpanel.product-import::validation.messages.image_failed', ['url' => $image->url, 'reason' => $reason])),
        ];

        if (! in_array($warning, $row->messages ?? [], true)) {
            $row->forceFill(['messages' => [...($row->messages ?? []), $warning]])->save();
        }
    }

    /**
     * A file name from the URL's last path segment.
     */
    protected function name(string $url): string
    {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);

        return Str::limit(Str::slug($name) ?: 'image', 100, '');
    }
}
