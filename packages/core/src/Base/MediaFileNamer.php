<?php

namespace Lunar\Base;

use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

class MediaFileNamer extends DefaultFileNamer
{
    /**
     * Slugify the base name of every newly added media file, so stored files get URL-safe names.
     *
     * @param  string  $fileName  File name including extension.
     */
    public function originalFileName(string $fileName): string
    {
        return MediaFileName::slug(parent::originalFileName($fileName));
    }
}
