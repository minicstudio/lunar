<?php

namespace Lunar\Admin\Filament\Resources\SessionReplayResource\Pages;

use Lunar\Admin\Filament\Resources\SessionReplayResource;
use Lunar\Admin\Support\Pages\BaseViewRecord;

class ViewReplaySession extends BaseViewRecord
{
    protected static string $resource = SessionReplayResource::class;

    protected string $view = 'lunarpanel::resources.session-replay-resource.pages.view-replay-session';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }
}
