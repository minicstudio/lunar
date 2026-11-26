<?php

namespace Lunar\ProductImport\Reading;

/**
 * A UTF-8 copy of an uploaded CSV file, with its detected delimiter.
 */
final readonly class PreparedCsv
{
    public function __construct(
        public string $path,
        public string $delimiter,
        public bool $converted,
    ) {}

    /**
     * Delete the temporary copy.
     */
    public function cleanup(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
