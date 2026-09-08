<?php

namespace Lunar\ProductFeed\Encoders;

use Illuminate\Support\Str;
use Lunar\ProductFeed\GoogleProductFeedAttributes;

class CsvFeedEncoder implements ProductFeedEncoder
{
    /**
     * Google Merchant columns plus Lunar `discount_name` for spreadsheet consumers.
     *
     * @var list<string>
     */
    protected array $headers;

    public function __construct()
    {
        $this->headers = [
            ...GoogleProductFeedAttributes::KEYS,
            'discount_name',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function encode(iterable $items): string
    {
        $handle = fopen('php://temp', 'r+');

        $this->streamEncode($items, $handle);

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * {@inheritdoc}
     */
    public function streamEncode(iterable $items, mixed $stream): void
    {
        fputcsv($stream, $this->headers);

        foreach ($items as $item) {
            $row = [];

            foreach ($this->headers as $header) {
                $value = $item[$header] ?? '';
                $row[] = is_scalar($value) ? (string) $value : '';
            }

            fputcsv($stream, $row);
        }
    }

    public function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function downloadFilename(): ?string
    {
        $shopName = Str::slug((string) config('app.name', 'shop')) ?: 'shop';

        return sprintf('%s-product-feed-%s.csv', $shopName, now()->format('Y-m-d'));
    }
}
