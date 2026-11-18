<?php

namespace Lunar\Admin\Support\Actions\ProductOptions;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Lunar\Models\Contracts\ProductOption as ProductOptionContract;
use Lunar\Models\Contracts\ProductOptionValue as ProductOptionValueContract;

class PreventLinkedDeletion
{
    public static function single(string $translationKey): Closure
    {
        return function (Action $action, Model $record) use ($translationKey): void {
            static::haltWhenLinked($action, collect([$record]), $translationKey);
        };
    }

    public static function bulk(string $translationKey): Closure
    {
        return function (Action $action, Collection $records) use ($translationKey): void {
            static::haltWhenLinked($action, $records, $translationKey);
        };
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    protected static function haltWhenLinked(Action $action, Collection $records, string $translationKey): void
    {
        $isLinked = $records->contains(
            fn (Model $record): bool => static::isLinked($record)
        );

        if (! $isLinked) {
            return;
        }

        Notification::make()
            ->title(__($translationKey.'.title'))
            ->body(__($translationKey.'.body'))
            ->danger()
            ->send();

        $action->halt();
    }

    public static function isLinked(Model $record): bool
    {
        if ($record instanceof ProductOptionContract) {
            return $record->products()->exists()
                || $record->values()->whereHas('variants', function (Builder $variantsQuery): void {
                    static::constrainToActiveProducts($variantsQuery);
                })->exists();
        }

        if ($record instanceof ProductOptionValueContract) {
            return static::constrainToActiveProducts($record->variants())->exists();
        }

        return false;
    }

    /**
     * Variants stay in place when their product is soft-deleted, so a variant link only counts while that product is active.
     */
    protected static function constrainToActiveProducts(Builder|Relation $variants): Builder|Relation
    {
        return $variants->whereHas('product', function (Builder $productQuery): void {
            $productQuery->withoutTrashed();
        });
    }
}
