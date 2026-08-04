<?php

namespace Lunar\Base;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaWebpConverter
{
    /**
     * Convert uploaded image binary contents to WebP when needed.
     *
     * @param  string  $contents  Raw uploaded file contents.
     * @param  string  $originalFileName  Original client file name including extension.
     * @return array{contents: string, file_name: string, converted: bool}
     */
    public static function convertUpload(string $contents, string $originalFileName): array
    {
        $extension = Str::lower(File::extension($originalFileName));

        if ($extension === 'webp') {
            return [
                'contents' => $contents,
                'file_name' => $originalFileName,
                'converted' => false,
            ];
        }

        $inputPath = self::temporaryPath(filled($extension) ? $extension : 'png');
        $outputPath = self::temporaryPath('webp');

        try {
            File::put($inputPath, $contents);

            Image::load($inputPath)
                ->format('webp')
                ->save($outputPath);

            return [
                'contents' => File::get($outputPath),
                'file_name' => self::webpFileName($originalFileName),
                'converted' => true,
            ];
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }

    /**
     * Replace a media item's original file with a WebP version on disk.
     *
     * @return bool True when the original was converted, false when already WebP.
     *
     * @throws RuntimeException When the original media file cannot be read.
     */
    public static function convertMediaOriginal(Media $media): bool
    {
        $extension = Str::lower(File::extension($media->file_name));

        if ($extension === 'webp') {
            return false;
        }

        $inputPath = self::temporaryPath(filled($extension) ? $extension : 'png');
        $outputPath = self::temporaryPath('webp');
        $newFileName = self::webpFileName($media->file_name);
        $oldRelativePath = $media->getPathRelativeToRoot();

        try {
            self::copyOriginalToTemporaryPath($media, $inputPath);

            Image::load($inputPath)
                ->format('webp')
                ->save($outputPath);

            resolve(Filesystem::class)->copyToMediaLibrary($outputPath, $media, null, $newFileName);

            if (File::basename($oldRelativePath) !== $newFileName) {
                Storage::disk($media->disk)->delete($oldRelativePath);
            }

            $media->update([
                'file_name' => $newFileName,
                'mime_type' => 'image/webp',
                'size' => File::size($outputPath) ?: $media->size,
            ]);

            return true;
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }

    /**
     * Copy a media original to a local temporary path for processing.
     *
     * @throws RuntimeException When the original media file cannot be read.
     */
    protected static function copyOriginalToTemporaryPath(Media $media, string $targetPath): void
    {
        $absolutePath = $media->getPath();

        if (is_string($absolutePath) && is_file($absolutePath)) {
            File::copy($absolutePath, $targetPath);

            return;
        }

        $relativePath = $media->getPathRelativeToRoot();
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($relativePath)) {
            throw new RuntimeException(
                "Media file [{$relativePath}] does not exist on disk [{$media->disk}] for media id {$media->getKey()}."
            );
        }

        $stream = $disk->readStream($relativePath);

        if (is_resource($stream)) {
            try {
                $contents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }

            if ($contents !== false && $contents !== '') {
                File::put($targetPath, $contents);

                return;
            }
        }

        $contents = $disk->get($relativePath);

        if ($contents === null || $contents === '') {
            throw new RuntimeException(
                "Unable to read media file [{$relativePath}] from disk [{$media->disk}] for media id {$media->getKey()}."
            );
        }

        File::put($targetPath, $contents);
    }

    /**
     * Generate a WebP file name from a given file name.
     *
     * @param  string  $fileName  Source file name including extension.
     */
    protected static function webpFileName(string $fileName): string
    {
        return File::name($fileName).'.webp';
    }

    /**
     * Generate a temporary path for a given extension.
     *
     * @param  string  $extension  File extension without a leading dot.
     */
    protected static function temporaryPath(string $extension): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
    }
}
