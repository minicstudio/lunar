<?php

namespace Lunar\Feedback\Filament\Extensions;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ViewPageExtension;

class ManageOrderExtension extends ViewPageExtension
{
    /**
     * Append the checkout shopping-experience score (and optional comment) to the order summary.
     */
    public function extendOrderSummaryInfolist(Section $section): Section
    {
        return $section->schema([
            ...$section->getDefaultChildComponents(),
            TextEntry::make('feedback_score')
                ->label(__('lunarpanel.feedback::plugin.order.score'))
                ->default(fn (Model $record) => $record->shoppingExperienceFeedback?->score)
                ->placeholder('-')
                ->alignEnd(),
            TextEntry::make('feedback_comment')
                ->label(__('lunarpanel.feedback::plugin.order.comment'))
                ->default(fn (Model $record) => $record->shoppingExperienceFeedback?->comment)
                ->alignEnd()
                ->visible(fn (Model $record) => filled($record->shoppingExperienceFeedback?->comment)),
        ]);
    }
}
