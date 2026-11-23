<?php

namespace Lunar\Admin\Filament\Resources\SessionReplayResource\Pages;

use Lunar\Admin\Filament\Resources\SessionReplayResource;
use Lunar\Admin\Support\Pages\BaseListRecords;

class ListReplaySessions extends BaseListRecords
{
    protected static string $resource = SessionReplayResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }
}
