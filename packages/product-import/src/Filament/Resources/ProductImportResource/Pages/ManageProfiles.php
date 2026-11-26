<?php

namespace Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Lunar\ProductImport\Actions\DeleteProfile;
use Lunar\ProductImport\Actions\RenameProfile;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Models\ProductImportProfile;

/**
 * Lists the saved import profiles, to rename or delete them.
 */
class ManageProfiles extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ProductImportResource::class;

    public function mount(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }

    public function getTitle(): string
    {
        return __('lunarpanel.product-import::ui.profiles.title');
    }

    /**
     * The page holds only the profiles table, in Filament's default page view.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductImportProfile::query())
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('lunarpanel.product-import::ui.profiles.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('header_count')
                    ->label(__('lunarpanel.product-import::ui.profiles.headers'))
                    ->state(fn (ProductImportProfile $record): int => count($record->headers ?? [])),
                TextColumn::make('usage_count')
                    ->label(__('lunarpanel.product-import::ui.profiles.usage'))
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label(__('lunarpanel.product-import::ui.profiles.last_used'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('rename')
                    ->label(__('lunarpanel.product-import::ui.profiles.rename'))
                    ->fillForm(fn (ProductImportProfile $record): array => ['name' => $record->name])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('lunarpanel.product-import::ui.profiles.name'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, ProductImportProfile $record, Action $action): void {
                        try {
                            app(RenameProfile::class)->handle($record, $data['name']);
                        } catch (ImportActionRefused $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                            $action->halt();
                        }
                    }),
                Action::make('delete')
                    ->label(__('lunarpanel.product-import::ui.profiles.delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (ProductImportProfile $record) => app(DeleteProfile::class)->handle($record)),
            ]);
    }
}
