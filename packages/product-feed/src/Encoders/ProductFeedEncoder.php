<?php

namespace Lunar\ProductFeed\Encoders;

interface ProductFeedEncoder
{
    /**
     * @param  iterable<int, array<string, mixed>>  $items
     */
    public function encode(iterable $items): string;

    /**
     * Write the full encoded body to an open writable stream. Does not close the stream.
     *
     * @param  iterable<int, array<string, mixed>>  $items
     * @param  resource  $stream
     */
    public function streamEncode(iterable $items, mixed $stream): void;

    public function contentType(): string;

    /**
     * Suggested download filename, or null when the response should not force a download name.
     */
    public function downloadFilename(): ?string;
}
