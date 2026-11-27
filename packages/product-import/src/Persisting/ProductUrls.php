<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\Url;

/**
 * Gives products the URL slugs of their file. A new product's slug replaces
 * the URL Lunar generated from its name in that language. An existing
 * product keeps its URLs and gets a slug only in a language where it has
 * none, because changing a live URL breaks links. A slug that another
 * product uses in that language gets a number suffix, the way Lunar's URL
 * generator makes one (`rochie-vara-2`), since a slug is unique per element
 * type and language.
 */
class ProductUrls
{
    /**
     * Language ids by code, loaded once.
     *
     * @var ?array<string, int>
     */
    protected ?array $languageIds = null;

    /**
     * Give the product its slugs.
     *
     * @param  array<string, string>  $slugs  Language code => slug.
     * @param  bool  $created  Whether the import just created the product.
     */
    public function write(Product $product, array $slugs, bool $created): void
    {
        foreach ($slugs as $locale => $slug) {
            $languageId = $this->languageId($locale);

            if ($languageId === null || $slug === '') {
                continue;
            }

            $urls = $product->urls()->where('language_id', $languageId)->orderByDesc('default')->orderBy('id')->get();

            if ((! $created && $urls->isNotEmpty()) || $urls->contains('slug', $slug)) {
                continue;
            }

            $unique = $this->unique($slug, $languageId, $product->id);
            $generated = $urls->first();

            if ($generated !== null) {
                $generated->update(['slug' => $unique]);

                continue;
            }

            $product->urls()->create(['language_id' => $languageId, 'slug' => $unique, 'default' => true]);
        }
    }

    /**
     * The slug, or the slug with the next number suffix when another product
     * uses it in the language.
     */
    public function unique(string $slug, int $languageId, ?int $productId): string
    {
        return static::suffixed($slug, $this->taken($slug, $languageId, $productId));
    }

    /**
     * The slug when it is not taken, else the slug with a number suffix one
     * above the highest the taken slugs carry, starting at 2.
     *
     * @param  list<string>  $taken
     */
    public static function suffixed(string $slug, array $taken): string
    {
        if (! in_array($slug, $taken, true)) {
            return $slug;
        }

        $highest = 1;

        foreach ($taken as $used) {
            if (Str::isMatch('/^'.preg_quote($slug, '/').'-(\d+)$/', $used)) {
                $highest = max($highest, (int) Str::afterLast($used, '-'));
            }
        }

        return $slug.'-'.($highest + 1);
    }

    /**
     * The slugs of other products in the language that equal the slug or
     * carry it with a suffix.
     *
     * @return list<string>
     */
    public function taken(string $slug, int $languageId, ?int $productId): array
    {
        return $this->productUrls($languageId)
            ->when($productId !== null, fn (Builder $query) => $query->where('element_id', '!=', $productId))
            ->where(fn (Builder $query) => $query->where('slug', $slug)->orWhere('slug', 'like', $slug.'-%'))
            ->pluck('slug')
            ->map(fn (mixed $used): string => (string) $used)
            ->all();
    }

    /**
     * A product's slugs in the language, the default first.
     *
     * @return list<string>
     */
    public function slugsOf(int $productId, int $languageId): array
    {
        return $this->productUrls($languageId)
            ->where('element_id', $productId)
            ->orderByDesc('default')
            ->orderBy('id')
            ->pluck('slug')
            ->map(fn (mixed $slug): string => (string) $slug)
            ->all();
    }

    /**
     * The id of a store language, or null when the store has no such language.
     */
    public function languageId(string $locale): ?int
    {
        $this->languageIds ??= Language::query()->pluck('id', 'code')->map(fn (mixed $id): int => (int) $id)->all();

        return $this->languageIds[$locale] ?? null;
    }

    /**
     * The URLs of products in a language.
     *
     * @return Builder<Url>
     */
    protected function productUrls(int $languageId): Builder
    {
        return Url::query()
            ->where('element_type', Product::morphName())
            ->where('language_id', $languageId);
    }
}
