<?php

namespace Lunar\ProductImport\Filament\Resources;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\ProductImport\Database\State\EnsureProductImportPermissions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ListProductImports;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ManageProfiles;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ViewProductImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Persisting\ImportResult;

class ProductImportResource extends BaseResource
{
    /**
     * The staff permission that gates every import page.
     */
    protected static ?string $permission = EnsureProductImportPermissions::PERMISSION;

    /**
     * The model associated with the resource.
     */
    protected static ?string $model = ProductImport::class;

    /**
     * Sort the item after the other catalog entries.
     */
    protected static ?int $navigationSort = 90;

    /**
     * Every page and action of the resource needs the import permission.
     * Filament's default would allow everything for a model without a policy.
     */
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return static::staffMayImport() ? Response::allow() : Response::deny();
    }

    /**
     * Whether the signed-in staff member holds the import permission (admins always do).
     */
    public static function staffMayImport(): bool
    {
        return (bool) Filament::auth()->user()?->can(static::$permission);
    }

    /**
     * Get the label for the resource.
     */
    public static function getLabel(): string
    {
        return __('lunarpanel.product-import::plugin.label');
    }

    /**
     * Get the plural label for the resource.
     */
    public static function getPluralLabel(): string
    {
        return __('lunarpanel.product-import::plugin.plural_label');
    }

    /**
     * Get the icon for the resource in the navigation.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-up-tray';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    /**
     * Imports are created through the upload action, never through a form.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Imports change only through their steps.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Imports are kept as history.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Imports are kept as history.
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Get the default table schema for the resource.
     */
    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('files')
                    ->label(__('lunarpanel.product-import::ui.list.file'))
                    ->state(fn (ProductImport $record): string => $record->fileLabel())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'files',
                        fn (Builder $files): Builder => $files->where('original_name', 'like', '%'.$search.'%'),
                    ))
                    ->wrap(),
                TextColumn::make('staff.fullName')
                    ->label(__('lunarpanel.product-import::ui.list.staff'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('lunarpanel.product-import::ui.list.uploaded_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('lunarpanel.product-import::ui.list.status'))
                    ->badge()
                    ->formatStateUsing(fn (ImportStatus $state): string => $state->label())
                    ->color(fn (ImportStatus $state): string => $state->color()),
                TextColumn::make('progress')
                    ->label(__('lunarpanel.product-import::ui.list.progress'))
                    ->state(fn (ProductImport $record): ?string => static::progress($record))
                    ->placeholder('-'),
                TextColumn::make('result')
                    ->label(__('lunarpanel.product-import::ui.list.result'))
                    ->state(fn (ProductImport $record): ?string => static::result($record))
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['staff', 'files'])->orderByDesc('id'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ProductImport $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * The share of product groups processed, then of images downloaded,
     * while importing.
     */
    public static function progress(ProductImport $import): ?string
    {
        if ($import->status !== ImportStatus::Importing) {
            return null;
        }

        $progress = app(ImportResult::class)->progress($import);

        return $progress['phase'] === 'images'
            ? __('lunarpanel.product-import::ui.list.images_progress', ['percent' => $progress['percent']])
            : $progress['percent'].'%';
    }

    /**
     * A short result of a finished import.
     */
    public static function result(ProductImport $import): ?string
    {
        if (! in_array($import->status, [ImportStatus::Completed, ImportStatus::CompletedWithErrors, ImportStatus::Failed], true)) {
            return null;
        }

        $stats = $import->stats ?? [];

        return collect([
            __('lunarpanel.product-import::ui.list.new', ['count' => (int) ($stats['new_products'] ?? 0)]),
            __('lunarpanel.product-import::ui.list.updated', ['count' => (int) ($stats['updated_products'] ?? 0)]),
            ($stats['failed_rows'] ?? 0) > 0 ? trans_choice('lunarpanel.product-import::ui.list.errors', (int) $stats['failed_rows'], ['count' => (int) $stats['failed_rows']]) : null,
        ])->filter()->implode(' · ');
    }

    /**
     * Get the pages for the resource.
     */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListProductImports::route('/'),
            'profiles' => ManageProfiles::route('/profiles'),
            'view' => ViewProductImport::route('/{record}'),
        ];
    }
}
