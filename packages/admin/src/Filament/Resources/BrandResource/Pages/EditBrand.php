<?php

namespace Lunar\Admin\Filament\Resources\BrandResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Support\Pages\BaseEditRecord;

class EditBrand extends BaseEditRecord
{
    protected static string $resource = BrandResource::class;

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel::brand.pages.edit.navigation_label');
    }

    public function getTitle(): string
    {
        return __('lunarpanel::brand.pages.edit.title');
    }

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record, Actions\DeleteAction $action) {
                    if ($record->products->count() > 0) {
                        Notification::make()
                            ->warning()
                            ->body(__('lunarpanel::brand.action.delete.notification.error_protected'))
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
