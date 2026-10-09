<?php

namespace Lunar\Feedback\Filament\Extensions;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Admin\Support\Extending\ResourceExtension;

class OrderResourceExtension extends ResourceExtension
{
    /**
     * Insert the checkout shopping-experience score after the customer type column.
     */
    public function extendTable(Table $table): Table
    {
        $columns = [];

        foreach ($table->getColumns() as $name => $column) {
            $columns[] = $column;

            if ($name === 'new_customer') {
                $columns[] = TextColumn::make('shoppingExperienceFeedback.score')
                    ->label(__('lunarpanel.feedback::plugin.order.score'))
                    ->placeholder('-')
                    ->toggleable();
            }
        }

        return $table
            ->columns($columns)
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['shoppingExperienceFeedback'])
            );
    }
}
