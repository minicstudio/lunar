<?php

namespace Lunar\Blog\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Lunar\Models\Language;

interface BlogCategory
{
    public function blogPosts(): BelongsToMany;

    public function getBlogCategorySlug(Language $language): Collection;
}
