<?php

namespace Lunar\Review\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Models\Channel;
use Lunar\Review\Events\ReviewCreatedEvent;
use Lunar\Review\Events\ReviewDeletedEvent;
use Lunar\Review\Events\ReviewUpdatedEvent;
use Lunar\Review\Filament\Resources\ReviewResource;

class ChannelReviewRelationManager extends BaseRelationManager
{
    /**
     * Defines the relationship name.
     */
    protected static string $relationship = 'reviews';

    /**
     * Determines if the relation manager is read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Get the table title for the relation manager.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel.review::plugin.relationManagers.channel.title');
    }

    /**
     * Get the table heading for the relation manager.
     *
     * @return string|Htmlable|null The localized table heading.
     */
    protected function getTableHeading(): string|Htmlable|null
    {
        return __('lunarpanel.review::plugin.relationManagers.channel.heading');
    }

    /**
     * Returns the associated resource class.
     */
    public static function getResource(): string
    {
        return ReviewResource::class;
    }

    /**
     * Configures the table used in the relation manager.
     */
    public function getDefaultTable(Table $table): Table
    {
        return $table->query(
            fn () => $this->getRelationship()->getQuery()->forChannel()
        )
            ->columns(
                static::getRelationManagerTableColumns()
            )
            ->defaultSort('created_at', 'desc')
            ->filters([
                ReviewResource::getApprovedAtFilter(),
                ReviewResource::getRatingFilter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->after(function (Model $record) {
                        ReviewCreatedEvent::dispatch($record);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->after(function ($record) {
                        ReviewUpdatedEvent::dispatch($record);
                    }),
                DeleteAction::make()
                    ->after(function ($record) {
                        ReviewDeletedEvent::dispatch($record);
                    }),
            ]);
    }

    /**
     * Get the columns for the table.
     */
    public static function getRelationManagerTableColumns(): array
    {
        return [
            ReviewResource::getModelNameTableColumn(__('lunarpanel.review::plugin.table.channel.name.label')),
            ReviewResource::getRatingTableColumn(),
            ReviewResource::getApprovedAtTableColumn(),
        ];
    }

    /**
     * Configures the form used in the relation manager.
     */
    public function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                $this->getModelFormComponent(),
                ReviewResource::getOrderFormComponent($this->getOwnerRecord()->id),
                ReviewResource::getUserFormComponent($this->getOwnerRecord()?->user?->id),
                ReviewResource::getModelTypeFormComponent(Channel::morphName()),

                ReviewResource::getAttributeDataFormComponent(),
                ReviewResource::getApprovedAtToggle(),

                Section::make(__('lunarpanel.review::plugin.form.upload_images_section'))
                    ->schema([
                        ReviewResource::getImageUploadComponent(),
                    ])
                    ->collapsible()
                    ->hidden(function (callable $get) {
                        return ! $get('reviewable_id');
                    }),
            ])
            ->columns(1);
    }

    /**
     * Get the model selection form component.
     */
    protected function getModelFormComponent(): Component
    {
        return Hidden::make('reviewable_id')
            ->default($this->getOwnerRecord()?->channel?->id);
    }
}
