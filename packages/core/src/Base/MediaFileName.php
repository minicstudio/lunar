<?php

namespace Lunar\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Models\Contracts\Brand;
use Lunar\Models\Contracts\Channel;
use Lunar\Models\Contracts\Collection as CollectionContract;
use Lunar\Models\Contracts\Product;
use Lunar\Models\Contracts\ProductVariant;
use Lunar\Models\Language;
use Lunar\Review\Models\Contracts\Review;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaFileName
{
    /**
     * Maximum length of a slugified file base name.
     */
    public const MAX_LENGTH = 100;

    /**
     * Base name used when a name has no characters that survive slugification.
     */
    public const FALLBACK_NAME = 'image';

    /**
     * Turn a name into a URL-safe file base name (lowercase ASCII words joined by hyphens).
     */
    public static function slug(string $name): string
    {
        $slug = rtrim(Str::substr(Str::slug($name, '-', 'en'), 0, self::MAX_LENGTH), '-');

        return $slug !== '' ? $slug : self::FALLBACK_NAME;
    }

    /**
     * Build a slugified file name from the preferred name, or from the original
     * file name when no preferred name is given, keeping the lowercased extension.
     *
     * @param  string  $fileName  Original file name including extension.
     * @param  string|null  $preferredName  Name to build the file name from, e.g. the image alt text.
     */
    public static function make(string $fileName, ?string $preferredName = null): string
    {
        $baseName = filled($preferredName) ? $preferredName : pathinfo($fileName, PATHINFO_FILENAME);

        return self::withExtension(self::slug($baseName), pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Build a random file name that reveals nothing about the original file, keeping the lowercased extension.
     *
     * @param  string  $fileName  Original file name including extension.
     */
    public static function random(string $fileName): string
    {
        return self::withExtension(Str::lower((string) Str::ulid()), pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Resolve the human-readable name of the entity shown in an image.
     */
    public static function ownerName(?Model $owner): ?string
    {
        if ($owner instanceof Review) {
            return self::ownerName($owner->reviewable);
        }

        if ($owner instanceof ProductVariant) {
            return self::ownerName($owner->product);
        }

        $name = match (true) {
            $owner instanceof Product, $owner instanceof CollectionContract => $owner->translateAttribute('name', Language::getDefault()?->code),
            $owner instanceof Brand, $owner instanceof Channel => $owner->name,
            default => null,
        };

        return is_string($name) && filled($name) ? $name : null;
    }

    /**
     * Describe review images using the subject and title, and catalog images using their custom or owner name.
     */
    public static function altText(?Model $owner, ?string $preferredName = null): string
    {
        if ($owner instanceof Review) {
            return collect([self::ownerName($owner), $owner->translateAttribute('title', Language::getDefault()?->code)])
                ->filter(fn ($part) => is_string($part) && filled($part))
                ->implode(' - ');
        }

        return filled($preferredName) ? $preferredName : (self::ownerName($owner) ?? '');
    }

    /**
     * Name uploads and existing files consistently, preserving the unique identifier of review images.
     */
    public static function forOwner(string $fileName, ?Model $owner, ?string $preferredName = null, ?string $identifier = null): string
    {
        if ($owner instanceof Review) {
            $identifier ??= (string) Str::uuid();
            $name = rtrim(Str::substr(self::slug(self::ownerName($owner) ?? self::FALLBACK_NAME), 0, self::MAX_LENGTH - strlen($identifier) - 1), '-');

            return self::withExtension($name.'-'.$identifier, pathinfo($fileName, PATHINFO_EXTENSION));
        }

        return self::make($fileName, filled($preferredName) ? $preferredName : self::ownerName($owner));
    }

    /**
     * Build the target file name using the owner and any explicit image name.
     */
    public static function forMedia(Media $media): string
    {
        $name = $media->getCustomProperty('name');

        $fileName = self::forOwner($media->file_name, $media->model, is_string($name) ? $name : null, $media->uuid ?: (string) $media->getKey());

        if (pathinfo($fileName, PATHINFO_EXTENSION) === pathinfo($media->file_name, PATHINFO_EXTENSION)
            && preg_match('/^(.+)-([2-9]|[1-9][0-9]+)$/', pathinfo($media->file_name, PATHINFO_FILENAME), $matches)
            && $matches[1] === rtrim(Str::substr(pathinfo($fileName, PATHINFO_FILENAME), 0, self::MAX_LENGTH - strlen($matches[2]) - 1), '-')) {
            return $media->file_name;
        }

        return $fileName;
    }

    /**
     * Slugify the file name and append -2, -3, ... until it differs from every slugified taken file name.
     * Also reserve untruncated slugs to preserve suffixes beyond the slug length limit.
     *
     * @param  iterable<string>  $takenFileNames
     */
    public static function unique(string $fileName, iterable $takenFileNames): string
    {
        $fileName = self::make($fileName);
        $taken = collect($takenFileNames)->flatMap(fn (string $takenFileName): array => [
            self::make($takenFileName),
            self::withExtension(
                Str::slug(pathinfo($takenFileName, PATHINFO_FILENAME), '-', 'en'),
                pathinfo($takenFileName, PATHINFO_EXTENSION),
            ),
        ])->flip();
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $candidate = $fileName;

        for ($suffix = 2; $taken->has($candidate); $suffix++) {
            $candidateBase = rtrim(Str::substr($baseName, 0, self::MAX_LENGTH - strlen((string) $suffix) - 1), '-');
            $candidate = self::withExtension("{$candidateBase}-{$suffix}", $extension);
        }

        return $candidate;
    }

    /**
     * File names used by the media of the given owner model.
     *
     * @return Collection<int, string>
     */
    public static function usedBy(string $modelType, int|string $modelId, ?int $exceptMediaId = null): Collection
    {
        return Media::query()
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->when($exceptMediaId, fn ($query) => $query->whereKeyNot($exceptMediaId))
            ->pluck('file_name');
    }

    /**
     * Plan the file names of media items stored in the same directory, so no two files end up with the same name.
     *
     * @param  iterable<Media>  $mediaItems  Media items stored in the same directory.
     * @param  iterable<string>  $existingFileNames  File names found in that directory, e.g. on disk.
     * @return array<int, string> New file names keyed by media id, only for media whose file name changes.
     */
    public static function planRenames(iterable $mediaItems, iterable $existingFileNames = []): array
    {
        $mediaItems = collect($mediaItems);

        $usage = $mediaItems->countBy(fn (Media $media) => Str::lower($media->file_name))->all();

        foreach ($existingFileNames as $existingFileName) {
            $usage[Str::lower($existingFileName)] ??= 1;
        }

        $renames = [];

        foreach ($mediaItems as $media) {
            $usage[Str::lower($media->file_name)]--;

            $fileName = self::forMedia($media);

            if ($fileName !== $media->file_name) {
                $fileName = self::unique($fileName, array_keys(array_filter($usage)));
            }

            if ($fileName !== $media->file_name) {
                $renames[$media->getKey()] = $fileName;
            }

            $usage[Str::lower($fileName)] = ($usage[Str::lower($fileName)] ?? 0) + 1;
        }

        return $renames;
    }

    /**
     * Join a base name and an extension, lowercasing the extension.
     */
    protected static function withExtension(string $baseName, string $extension): string
    {
        return filled($extension) ? $baseName.'.'.Str::lower($extension) : $baseName;
    }
}
