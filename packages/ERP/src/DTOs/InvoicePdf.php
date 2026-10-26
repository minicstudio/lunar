<?php

namespace Lunar\ERP\DTOs;

class InvoicePdf
{
    /**
     * Create a new invoice PDF DTO.
     */
    public function __construct(
        public readonly string $content,
        public readonly string $filename,
        public readonly string $mimeType = 'application/pdf',
    ) {}
}
