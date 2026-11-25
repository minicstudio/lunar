<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Lunar\FieldTypes\Text;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\DownloadImages;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Persisting\HostResolver;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public']);
    Language::factory()->create(['code' => 'ro', 'default' => true]);

    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return [
                'img.example.com' => ['93.184.216.34'],
                'internal.example.com' => ['10.0.0.5'],
            ][$host] ?? [];
        }
    });

    $this->import = ProductImport::factory()->create(['status' => ImportStatus::Importing]);
    $sheet = ProductImportSheet::factory()->create(['product_import_id' => $this->import->id]);
    $this->row = ProductImportRow::factory()->create(['product_import_sheet_id' => $sheet->id, 'product_import_id' => $this->import->id]);
    $this->product = Product::factory()->create();
});

/**
 * A small image of the given type.
 */
function imageBytes(string $type = 'png'): string
{
    $image = imagecreatetruecolor(2, 2);
    ob_start();
    $type === 'jpeg' ? imagejpeg($image) : imagepng($image);

    return (string) ob_get_clean();
}

/**
 * Record image URLs of the product for the import, in order.
 *
 * @param  list<string>  $urls
 * @return list<ProductImportImage>
 */
function pendingImages(ProductImport $import, Product $product, ProductImportRow $row, array $urls, ?int $variantId = null): array
{
    return array_map(fn (string $url, int $position) => ProductImportImage::factory()->create([
        'product_import_id' => $import->id,
        'product_import_row_id' => $row->id,
        'product_id' => $product->id,
        'product_variant_id' => $variantId,
        'url' => $url,
        'position' => $position,
    ]), $urls, array_keys($urls));
}

/**
 * Run the download job for the images.
 *
 * @param  list<ProductImportImage>  $images
 */
function downloadImages(ProductImport $import, array $images): void
{
    app()->call([new DownloadImages($import->id, array_map(fn (ProductImportImage $image) => $image->id, $images)), 'handle']);
}

test('three images arrive in order with the first as the primary image, and a second run downloads nothing', function () {
    Http::fake([
        'https://img.example.com/*' => Http::response(imageBytes(), 200, ['Content-Type' => 'image/png']),
    ]);

    $urls = ['https://img.example.com/rv-1.png', 'https://img.example.com/rv-2.png', 'https://img.example.com/rv-3.png'];
    downloadImages($this->import, pendingImages($this->import, $this->product, $this->row, $urls));

    $media = $this->product->fresh()->getMedia(config('lunar.media.collection'));

    expect($media->map(fn ($item) => $item->getCustomProperty('source_url'))->all())->toBe($urls)
        ->and($media->map(fn ($item) => $item->getCustomProperty('primary'))->all())->toBe([true, false, false])
        ->and(ProductImportImage::query()->pluck('status')->unique()->all())->toBe([ImageStatus::Downloaded]);

    Http::assertSentCount(3);

    $second = ProductImport::factory()->create(['status' => ImportStatus::Importing]);
    downloadImages($second, pendingImages($second, $this->product, $this->row, $urls));

    Http::assertSentCount(3);

    expect($this->product->fresh()->getMedia(config('lunar.media.collection')))->toHaveCount(3)
        ->and(ProductImportImage::query()->where('product_import_id', $second->id)->pluck('status')->unique()->all())->toBe([ImageStatus::Existing]);
});

test('a variant image is linked to its variant, and is the primary image of a product without an image of its own', function () {
    Http::fake(['https://img.example.com/*' => Http::response(imageBytes('jpeg'), 200, ['Content-Type' => 'image/jpeg'])]);

    $variant = ProductVariant::factory()->for($this->product, 'product')->create();
    downloadImages($this->import, pendingImages($this->import, $this->product, $this->row, ['https://img.example.com/s.jpg'], $variant->id));

    expect($variant->images()->pluck('media.id')->all())->toBe([ProductImportImage::query()->sole()->media_id])
        ->and((bool) $variant->images()->first()->pivot->primary)->toBeTrue()
        ->and($this->product->fresh()->getFirstMedia(config('lunar.media.collection'))->getCustomProperty('primary'))->toBeTrue();
});

test('a product image and the variant images that repeat it share one file, and a variant image the product lacks is added to the product', function () {
    Http::fake(['https://img.example.com/*' => Http::response(imageBytes(), 200, ['Content-Type' => 'image/png'])]);

    $white = ProductVariant::factory()->for($this->product, 'product')->create();
    $black = ProductVariant::factory()->for($this->product, 'product')->create();
    $images = collect([
        ['https://img.example.com/white.png', null],
        ['https://img.example.com/white.png', $white->id],
        ['https://img.example.com/black.png', $black->id],
    ])->map(fn (array $image, int $position) => ProductImportImage::factory()->create([
        'product_import_id' => $this->import->id,
        'product_import_row_id' => $this->row->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $image[1],
        'url' => $image[0],
        'position' => $position,
    ]))->all();

    downloadImages($this->import, $images);

    $media = $this->product->fresh()->getMedia(config('lunar.media.collection'))->keyBy(fn ($item) => $item->getCustomProperty('source_url'));

    expect($media)->toHaveCount(2)
        ->and($white->images()->pluck('media.id')->all())->toBe([$media['https://img.example.com/white.png']->id])
        ->and($black->images()->pluck('media.id')->all())->toBe([$media['https://img.example.com/black.png']->id])
        ->and($media->map(fn ($item) => $item->getCustomProperty('primary'))->all())->toBe(['https://img.example.com/white.png' => true, 'https://img.example.com/black.png' => false]);

    Http::assertSentCount(2);
});

test('images whose urls end in the same file name keep their own files, named after the product', function () {
    Http::fake(['https://img.example.com/*' => Http::response(imageBytes(), 200, ['Content-Type' => 'image/png'])]);

    $product = Product::factory()->create(['attribute_data' => collect(['name' => new Text('Tricou basic')])]);
    downloadImages($this->import, pendingImages($this->import, $product, $this->row, [
        'https://img.example.com/id/1/800/1000.png',
        'https://img.example.com/id/2/800/1000.png',
    ]));

    $media = $product->fresh()->getMedia(config('lunar.media.collection'));

    expect($media->pluck('file_name')->all())->toBe(['tricou-basic.png', 'tricou-basic-2.png'])
        ->and($media->pluck('name')->unique()->all())->toBe(['Tricou basic'])
        ->and($media->map(fn ($item) => $item->getPath())->unique())->toHaveCount(2);
});

test('a missing image fails with a warning on its row', function () {
    Http::fake(['https://img.example.com/*' => Http::response('', 404)]);

    downloadImages($this->import, pendingImages($this->import, $this->product, $this->row, ['https://img.example.com/missing.jpg']));

    $image = ProductImportImage::query()->sole();

    expect($image->status)->toBe(ImageStatus::Failed)
        ->and($image->error)->toBe('the server answered with status 404')
        ->and(collect($this->row->fresh()->messages)->where('code', 'image_failed')->pluck('message')->all())
        ->toBe(['The image "https://img.example.com/missing.jpg" could not be downloaded: the server answered with status 404']);
});

test('local and private addresses, and redirects to them, are refused without a request to them', function () {
    Http::fake([
        'https://img.example.com/moved.png' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest']),
        '*' => Http::response(imageBytes(), 200, ['Content-Type' => 'image/png']),
    ]);

    downloadImages($this->import, pendingImages($this->import, $this->product, $this->row, [
        'http://127.0.0.1/admin.png',
        'https://internal.example.com/logo.png',
        'https://img.example.com/moved.png',
    ]));

    $errors = ProductImportImage::query()->orderBy('position')->pluck('error')->all();

    expect(ProductImportImage::query()->pluck('status')->unique()->all())->toBe([ImageStatus::Failed])
        ->and($errors)->toBe(array_fill(0, 3, 'the address is not allowed, because it is not a public web address'));

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '127.0.0.1')
        || str_contains($request->url(), 'internal.example.com')
        || str_contains($request->url(), '169.254.169.254'));
});

test('an image over the size limit and a page that is not an image are refused', function () {
    Http::fake([
        'https://img.example.com/large.jpg' => Http::response(str_repeat('x', 11 * 1024 * 1024), 200, ['Content-Type' => 'image/jpeg']),
        'https://img.example.com/page.jpg' => Http::response('<html><body>Not found</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    downloadImages($this->import, pendingImages($this->import, $this->product, $this->row, [
        'https://img.example.com/large.jpg',
        'https://img.example.com/page.jpg',
    ]));

    expect(ProductImportImage::query()->orderBy('position')->pluck('error')->all())->toBe([
        'it is larger than 10240 KB',
        'it is not a JPEG, PNG, GIF, or WebP image',
    ])
        ->and($this->product->fresh()->getMedia(config('lunar.media.collection')))->toHaveCount(0);
});
