<?php

namespace Lunar\ProductFeed\Encoders;

use Lunar\ProductFeed\GoogleProductFeedAttributes;

class XmlFeedEncoder implements ProductFeedEncoder
{
    /**
     * {@inheritdoc}
     */
    public function encode(iterable $items): string
    {
        $handle = fopen('php://temp', 'r+');

        $this->streamEncode($items, $handle);

        rewind($handle);
        $xml = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $xml;
    }

    /**
     * {@inheritdoc}
     */
    public function streamEncode(iterable $items, mixed $stream): void
    {
        $title = (string) config('lunar.product-feed.xml.title', 'Product Feed');
        $description = (string) config('lunar.product-feed.xml.description', 'Product catalog feed');
        $link = (string) config('lunar.product-feed.xml.link', config('app.url'));

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');
        $xml->writeElement('title', $title);
        $xml->writeElement('link', $link);
        $xml->writeElement('description', $description);

        // Flush document head before items so memory stays bounded while iterating.
        fwrite($stream, $xml->outputMemory(true));

        foreach ($items as $item) {
            $xml->startElement('item');

            $xml->writeElement('title', (string) ($item['title'] ?? ''));
            $xml->writeElement('link', (string) ($item['link'] ?? ''));
            $xml->writeElement('description', (string) ($item['description'] ?? ''));

            foreach (GoogleProductFeedAttributes::KEYS as $key) {
                if (! array_key_exists($key, $item)) {
                    continue;
                }

                $value = $item[$key];

                if (! is_scalar($value) || $value === '') {
                    continue;
                }

                $xml->startElement('g:'.$key);
                $xml->text((string) $value);
                $xml->endElement();
            }

            $xml->endElement(); // item

            fwrite($stream, $xml->outputMemory(true));
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        fwrite($stream, $xml->outputMemory(true));
    }

    public function contentType(): string
    {
        return 'application/xml; charset=UTF-8';
    }

    public function downloadFilename(): ?string
    {
        return null;
    }
}
