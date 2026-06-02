<?php

namespace Lunar\Admin\Support\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Admin\Events\ModelMediaUpdated;
use Lunar\Admin\Rules\SecureMediaUploadRule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'media';

    public string $mediaCollection = 'default';

    public ?string $pendingMediaPath = null;

    public ?string $pendingMediaName = null;

    public ?string $pendingMediaCustomName = null;

    public ?bool $pendingMediaPrimary = null;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function confirmNonWebpUpload(): void
    {
        $this->getOwnerRecord()
            ->addMediaFromString(file_get_contents($this->pendingMediaPath))
            ->usingFileName($this->pendingMediaName)
            ->withCustomProperties([
                'name' => $this->pendingMediaCustomName,
                'primary' => $this->pendingMediaPrimary,
            ])
            ->preservingOriginal()
            ->toMediaCollection($this->mediaCollection);

        $this->pendingMediaPath = null;
        $this->pendingMediaName = null;
        $this->pendingMediaCustomName = null;
        $this->pendingMediaPrimary = null;

        ModelMediaUpdated::dispatch($this->getOwnerRecord());

        $this->dispatch('close-modal', id: 'media-non-webp-warning');
        $this->unmountTableAction();
    }

    public function cancelNonWebpUpload(): void
    {
        $this->pendingMediaPath = null;
        $this->pendingMediaName = null;
        $this->pendingMediaCustomName = null;
        $this->pendingMediaPrimary = null;

        $this->dispatch('close-modal', id: 'media-non-webp-warning');
    }

    public function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('custom_properties.name')
                    ->label(__('lunarpanel::relationmanagers.medias.form.name.label'))
                    ->helperText(__('lunarpanel::relationmanagers.medias.form.name.helper_text'))
                    ->maxLength(255),
                Forms\Components\Toggle::make('custom_properties.primary')
                    ->label(__('lunarpanel::relationmanagers.medias.form.primary.label'))
                    ->inline(false),
                Forms\Components\FileUpload::make('media')
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
                Tables\Columns\ImageColumn::make('image')
                    ->state(function (Media $record): string {
                        return $record->hasGeneratedConversion('small') ? $record->getUrl('small') : '';
                    })
                    ->label(__('lunarpanel::relationmanagers.medias.table.image.label')),
                Tables\Columns\TextColumn::make('file_name')
                    ->limit(30)
                    ->label(__('lunarpanel::relationmanagers.medias.table.file.label')),
                Tables\Columns\TextColumn::make('custom_properties.name')
                    ->label(__('lunarpanel::relationmanagers.medias.table.name.label')),
                Tables\Columns\IconColumn::make('custom_properties.primary')
                    ->label(__('lunarpanel::relationmanagers.medias.table.primary.label'))
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('lunarpanel::relationmanagers.medias.actions.create.label'))
                    ->before(function (array $data, Tables\Actions\CreateAction $action) {
                        $ext = strtolower(pathinfo($data['media']->getClientOriginalName(), PATHINFO_EXTENSION));

                        if ($ext !== 'webp') {
                            $this->pendingMediaPath = $data['media']->getRealPath();
                            $this->pendingMediaName = $data['media']->getClientOriginalName();
                            $this->pendingMediaCustomName = $data['custom_properties']['name'] ?? null;
                            $this->pendingMediaPrimary = $data['custom_properties']['primary'] ?? false;

                            $this->dispatch('open-modal', id: 'media-non-webp-warning');
                            $action->halt();
                        }
                    })
                    ->using(function (array $data, string $model): Model {
                        return $this->getOwnerRecord()->addMediaFromString($data['media']->get())
                            ->usingFileName(
                                $data['media']->getClientOriginalName()
                            )
                            ->withCustomProperties([
                                'name' => $data['custom_properties']['name'],
                                'primary' => $data['custom_properties']['primary'],
                            ])
                            ->preservingOriginal()
                            ->toMediaCollection($this->mediaCollection);
                    })->after(
                        fn () => ModelMediaUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(
                    fn () => ModelMediaUpdated::dispatch(
                        $this->getOwnerRecord()
                    )
                ),
                Tables\Actions\DeleteAction::make(),
                Action::make('view_open')
                    ->label(__('lunarpanel::relationmanagers.medias.actions.view.label'))
                    ->icon('lucide-eye')
                    ->url(fn (Media $record): string => $record->getUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->after(
                        fn () => ModelMediaUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    ),
                ]),
            ])
            ->reorderable('order_column');
    }
}
