<?php

namespace Lunar\Blog\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface BlogPost
{
    public function author(): BelongsTo;

    public function blogCategories(): BelongsToMany;

    public function thumbnailDisk(): string;

    public function getThumbnail(): ?string;
}
