<?php

namespace Lunar\ProductFeed\Encoders;

class JsonFeedEncoder implements ProductFeedEncoder
{
    /**
     * Lunar-only / optional keys omitted when empty.
     *
     * @var list<string>
     */
    protected array $optionalKeys = [
        'image_link',
        'brand',
        'gtin',
        'mpn',
        'sale_price',
        'sale_price_effective_date',
        'size',
        'color',
        'material',
        'pattern',
        'product_type',
        'discount_name',
        'collections',
        'product_options',
        'review_score',
        'tags',
    ];

    /**
     * Price fields expanded to `{amount, currency}` objects for JSON consumers.
     *
     * @var list<string>
     */
    protected array $structuredPriceKeys = [
        'price',
        'sale_price',
    ];

    /**
     * {@inheritdoc}
     */
    public function encode(iterable $items): string
    {
        $handle = fopen('php://temp', 'r+');

        $this->streamEncode($items, $handle);

        rewind($handle);
        $json = stream_get_contents($handle) ?: '[]';
        fclose($handle);

        return $json;
    }

    /**
     * {@inheritdoc}
     */
    public function streamEncode(iterable $items, mixed $stream): void
    {
        fwrite($stream, '[');

        $first = true;

        foreach ($items as $item) {
            $row = $this->mapItem($item);

            if (! $first) {
                fwrite($stream, ',');
            }

            $first = false;

            fwrite(
                $stream,
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
            );
        }

        fwrite($stream, ']');
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function downloadFilename(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function mapItem(array $item): array
    {
        $row = [];

        foreach ($item as $key => $value) {
            if ($this->shouldOmit($key, $value)) {
                continue;
            }

            if (in_array($key, $this->structuredPriceKeys, true) && is_string($value)) {
                $structured = $this->parseAmountCurrency($value);

                if ($structured !== null) {
                    $row[$key] = $structured;

                    continue;
                }
            }

            $row[$key] = $value;
        }

        return $row;
    }

    protected function shouldOmit(string $key, mixed $value): bool
    {
        if (! in_array($key, $this->optionalKeys, true)) {
            return false;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || $value === '';
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    protected function parseAmountCurrency(string $value): ?array
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)\s+([A-Z]{3})$/', trim($value), $matches)) {
            return null;
        }

        return [
            'amount' => $matches[1],
            'currency' => $matches[2],
        ];
    }
}
