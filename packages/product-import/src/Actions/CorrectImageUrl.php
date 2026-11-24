<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportImage;

/**
 * Changes the URL of a failed image of a finished import, for a retry of the
 * failed images. Only HTTP and HTTPS addresses are accepted. The row's raw
 * cell keeps the original URL.
 */
class CorrectImageUrl
{
    use GuardsImportStatus;

    /**
     * @throws ValidationException
     */
    public function handle(ProductImportImage $image, string $url): ProductImportImage
    {
        $import = $image->import;

        $this->ensureStatus($import, [ImportStatus::Completed, ImportStatus::CompletedWithErrors, ImportStatus::Failed]);

        if ($import->isPruned()) {
            throw ImportActionRefused::because('pruned');
        }

        if ($image->status !== ImageStatus::Failed) {
            throw ImportActionRefused::because('image_not_failed');
        }

        $url = Str::trim($url);

        if (! Str::isUrl($url, ['http', 'https']) || Str::length($url) > 2048) {
            throw ValidationException::withMessages(['url' => __('lunarpanel.product-import::errors.image_url_invalid')]);
        }

        $image->forceFill(['url' => $url])->save();

        return $image;
    }
}
