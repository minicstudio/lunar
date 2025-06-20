<?php

namespace Lunar\Admin\Filament\Resources\CollectionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Forms;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Filament\Resources\CollectionGroupResource;
use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Facades\DB;
use Lunar\Models\Collection;

class EditCollection extends BaseEditRecord
{
    protected static string $resource = CollectionResource::class;

    public static bool $formActionsAreSticky = true;

    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel::collection.pages.edit.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel::collection.pages.edit.label');
    }

    public function getBreadcrumbs(): array
    {
        return static::getResource()::getCollectionBreadcrumbs(
            $this->getRecord()
        );
    }

    protected function getDefaultHeaderActions(): array
    {
        $record = $this->getRecord();

        $successUrl = CollectionGroupResource::getUrl('edit', [
            'record' => $record->group,
        ]);

        if ($record->parent) {
            $successUrl = CollectionResource::getUrl('edit', [
                'record' => $record->parent,
            ]);
        }

        return [
            DeleteAction::make('delete')->form([
                Forms\Components\Select::make('target_collection')
                    ->label(__('lunarpanel::collection.pages.edit.actions.delete.select'))
                    ->model(Collection::class)
                    ->searchable()
                    ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search) use ($record): array {
                        return get_search_builder(Collection::class, $search)
                            ->get()
                            ->reject(
                                fn ($result) => $result->isDescendantOf($record)
                            )
                            ->mapWithKeys(fn (Collection $record): array => [$record->getKey() => $record->translateAttribute('name')])
                            ->all();
                    })->helperText(
                        __('lunarpanel::collection.pages.edit.actions.delete.helper_text')
                    )->hidden(
                        fn () => ! $record->children()->count()
                    ),
            ])->before(function (Collection $collection, array $data) {

                $targetId = $data['target_collection'] ?? null;

                if ($targetId) {
                    $parent = Collection::find($targetId);

                    DB::beginTransaction();
                    foreach ($collection->children as $child) {
                        $child->prependToNode($parent)->save();
                    }
                    DB::commit();

                } else {
                    $collection->descendants()->delete();
                }
            })->successRedirectUrl($successUrl),
        ];
    }
}
