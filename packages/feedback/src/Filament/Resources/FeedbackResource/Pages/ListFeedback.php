<?php

namespace Lunar\Feedback\Filament\Resources\FeedbackResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Feedback\Filament\Resources\FeedbackResource;

class ListFeedback extends BaseListRecords
{
    /**
     * The resource class for the feedback list.
     */
    protected static string $resource = FeedbackResource::class;
}
