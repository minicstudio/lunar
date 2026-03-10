<?php

namespace Lunar\Blog\Traits;

use Illuminate\Support\Collection;

trait ResolvesSlug
{
    /**
     * Get the slugs for the given language ID.
     */
    public function getSlugs(int $languageId): Collection
    {
        return $this->urls()
            ->whereLanguageId($languageId)
            ->orderByDesc('default')
            ->pluck('slug');
    }
}
