<?php

namespace Lunar\ProductImport\Mapping;

use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * Compares headers without letter case, accents, and surrounding spaces.
 */
class HeaderNormalizer
{
    /**
     * The comparison form of one header.
     */
    public function normalize(?string $header): string
    {
        return KeyNormalizer::key($header);
    }

    /**
     * The distinct, non-empty comparison forms of a list of headers.
     *
     * @param  iterable<?string>  $headers
     * @return list<string>
     */
    public function normalizeAll(iterable $headers): array
    {
        $normalized = [];

        foreach ($headers as $header) {
            $key = $this->normalize($header);

            if ($key !== '') {
                $normalized[$key] = true;
            }
        }

        return array_keys($normalized);
    }
}
