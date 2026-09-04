<?php

namespace Lunar\Admin\Support\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Admin\Events\ModelMediaUpdated;
use Lunar\Admin\Rules\SecureMediaUploadRule;
use Lunar\Base\MediaWebpConverter;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'media';

    public string $mediaCollection = 'default';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getDefaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('custom_properties.name')
                    ->label(__('lunarpanel::relationmanagers.medias.form.name.label'))
                    ->helperText(__('lunarpanel::relationmanagers.medias.form.name.helper_text'))
                    ->maxLength(255),
                Toggle::make('custom_properties.primary')
                    ->label(__('lunarpanel::relationmanagers.medias.form.primary.label'))
                    ->inline(false),
                FileUpload::make('media')
                    ->label(__('lunarpanel::relationmanagers.medias.form.media.label'))
                    ->columnSpan(2)
                    ->hiddenOn('edit')
                    ->storeFiles(false)
                    ->imageEditor()
                    ->required()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->acceptedFileTypes(config('lunar.media.accepted_file_types', []))
                    ->rules([
                        'file',
                        'max:'.(config('lunar.media.max_file_size', 10240) ?: 10240), // 10MB default
                        new SecureMediaUploadRule,
                    ]),
            ]);
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->heading(function () {
                return __($this->getOwnerRecord()->getMediaCollectionTitle($this->mediaCollection) ?? Str::ucfirst($this->mediaCollection));
            })
            ->description(function () {
                return $this->getOwnerRecord()->getMediaCollectionDescription($this->mediaCollection) ?? '';
            })
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('collection_name', $this->mediaCollection)->orderBy('order_column'))
            ->columns([
                ImageColumn::make('image')
                    ->state(function (Media $record): string {
                        return $record->hasGeneratedConversion('small') ? $record->getUrl('small') : '';
                    })
                    ->label(__('lunarpanel::relationmanagers.medias.table.image.label')),
                TextColumn::make('file_name')
                    ->limit(30)
                    ->label(__('lunarpanel::relationmanagers.medias.table.file.label')),
                TextColumn::make('custom_properties.name')
                    ->label(__('lunarpanel::relationmanagers.medias.table.name.label')),
                IconColumn::make('custom_properties.primary')
                    ->label(__('lunarpanel::relationmanagers.medias.table.primary.label'))
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('lunarpanel::relationmanagers.medias.actions.create.label'))
                    ->using(function (array $data, string $model): Model {
                        $converted = MediaWebpConverter::convertUpload(
                            $data['media']->get(),
                            $data['media']->getClientOriginalName()
                        );

                        $media = $this->getOwnerRecord()->addMediaFromString($converted['contents'])
                            ->usingFileName($converted['file_name'])
                            ->withCustomProperties([
                                'name' => $data['custom_properties']['name'],
                                'primary' => $data['custom_properties']['primary'],
                            ])
                            ->preservingOriginal()
                            ->toMediaCollection($this->mediaCollection);

                        if ($converted['converted']) {
                            Notification::make()
                                ->title(__('lunarpanel::relationmanagers.medias.notifications.webp_conversion.title'))
                                ->body(__('lunarpanel::relationmanagers.medias.notifications.webp_conversion.body'))
                                ->success()
                                ->send();
                        }

                        return $media;
                    })->after(
                        fn () => ModelMediaUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data, Media $record): array {
                        $data['custom_properties'] = array_merge(
                            $record->custom_properties ?? [],
                            $data['custom_properties'] ?? [],
                        );

                        return $data;
                    })
                    ->after(
                        fn () => ModelMediaUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    ),
                DeleteAction::make(),
                Action::make('view_open')
                    ->label(__('lunarpanel::relationmanagers.medias.actions.view.label'))
                    ->icon('lucide-eye')
                    ->url(fn (Media $record): string => $record->getUrl())
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(
                        fn () => ModelMediaUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    ),
                ]),
            ])
            ->reorderable('order_column');
    }
}
