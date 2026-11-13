<?php

namespace Lunar\Admin\Support\Livewire;

use Livewire\Component;

use function Livewire\store;

class FileDownload
{
    /**
     * Queue a file for the browser to download.
     *
     * A Symfony response returned from a Livewire action is copied into the
     * JSON payload. PHP cannot encode that object, so the request fails after
     * the file has already been fetched. Livewire delivers the file through
     * its download effect instead.
     */
    public static function queue(Component $component, string $contents, string $name, string $contentType = 'application/pdf'): void
    {
        store($component)->set('download', [
            'name' => $name,
            'content' => base64_encode($contents),
            'contentType' => $contentType,
        ]);
    }
}
