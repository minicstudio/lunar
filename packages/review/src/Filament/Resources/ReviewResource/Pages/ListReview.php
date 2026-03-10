<?php

namespace Lunar\Review\Filament\Resources\ReviewResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Review\Filament\Resources\ReviewResource;

class ListReview extends BaseListRecords
{
    /**
     * The resource class for the review.
     */
    protected static string $resource = ReviewResource::class;
}
