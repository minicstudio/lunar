<?php

namespace Lunar\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lunar\Base\Traits\HasMedia;
use Lunar\Models\Language;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

class ContentBlock extends Model implements SpatieHasMedia
{
    use HasMedia;

    protected $fillable = [
        'type',
        'key',
        'data',
        'is_active',
        'sort_order',
        'channel_id',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Scope to filter by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by key.
     */
    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Scope to only active records, respecting optional schedule.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Scope to order by sort_order ascending.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Translate a locale-keyed value stored inside the data JSON column.
     */
    public function translateData(string $key, ?string $locale = null): ?string
    {
        $values = Arr::get($this->data ?? [], $key);

        if (is_string($values)) {
            return $values;
        }

        if (! $values) {
            return null;
        }

        $locale = $locale ?: app()->getLocale();

        $value = Arr::accessible($values)
            ? Arr::get($values, $locale)
            : (get_object_vars($values)[$locale] ?? null);

        if (filled($value)) {
            return (string) $value;
        }

        $fallback = Arr::get($values, app()->getLocale(), Arr::first(Arr::wrap($values)));

        return filled($fallback) ? (string) $fallback : null;
    }

    /**
     * Convert legacy plain-string data fields into locale maps for admin forms.
     *
     * Keys may be dotted paths (e.g. `address.street`) for nested JSON.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function wrapPlainStringsAsTranslations(array $data, array $keys): array
    {
        $locale = Language::getDefault()?->code ?? app()->getLocale();

        foreach ($keys as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value)) {
                Arr::set($data, $key, [$locale => $value]);
            }
        }

        return $data;
    }

    /**
     * Resolve the storefront URL for the hero image.
     */
    public function heroImageUrl(?string $conversion = null): ?string
    {
        $media = $this->getFirstMedia('heroes');

        if (! $media) {
            return null;
        }

        if ($conversion && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        if (! $conversion && $media->hasGeneratedConversion('large')) {
            return $media->getUrl('large');
        }

        return $media->getUrl();
    }
}
