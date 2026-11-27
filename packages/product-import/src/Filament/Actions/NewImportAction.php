<?php

namespace Lunar\ProductImport\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lunar\ProductImport\Actions\CreateImportFromUpload;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Reading\CsvPreparer;

/**
 * The upload modal shared by the imports list and the product list: one or
 * more files that become one import. It is disabled while the AI analysis is
 * not configured.
 */
class NewImportAction
{
    /**
     * The file types the upload accepts.
     *
     * @var list<string>
     */
    public const MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'application/csv',
    ];

    public static function make(string $name = 'newImport'): Action
    {
        return Action::make($name)
            ->label(__('lunarpanel.product-import::ui.actions.new_import'))
            ->icon('heroicon-o-arrow-up-tray')
            ->disabled(fn (): bool => ! app(AiAvailability::class)->isConfigured())
            ->tooltip(fn (): ?string => app(AiAvailability::class)->isConfigured() ? null : __('lunarpanel.product-import::analysis.failures.not_configured'))
            ->modalHeading(__('lunarpanel.product-import::ui.upload.heading'))
            ->modalDescription(fn (): string => app(AiAvailability::class)->label())
            ->modalSubmitActionLabel(__('lunarpanel.product-import::ui.upload.submit'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('lunarpanel.product-import::ui.upload.file'))
                    ->helperText(__('lunarpanel.product-import::ui.upload.help', [
                        'size' => (int) round((int) config('lunar.product-import.max_file_size_kb', 20480) / 1024),
                    ]))
                    ->acceptedFileTypes(self::MIME_TYPES)
                    ->maxSize((int) config('lunar.product-import.max_file_size_kb', 20480))
                    ->multiple()
                    ->reorderable()
                    ->storeFiles(false)
                    ->required(),
                Select::make('encoding')
                    ->label(__('lunarpanel.product-import::ui.upload.encoding'))
                    ->helperText(__('lunarpanel.product-import::ui.upload.encoding_help'))
                    ->options(array_combine(CsvPreparer::ENCODINGS, CsvPreparer::ENCODINGS))
                    ->default('UTF-8')
                    ->selectablePlaceholder(false),
            ])
            ->action(function (array $data, Action $action) {
                $files = array_values(array_filter(Arr::wrap($data['file'] ?? [])));

                try {
                    $import = app(CreateImportFromUpload::class)->handle($files, Filament::auth()->user(), $data['encoding'] ?? null);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return null;
                }

                return redirect(ProductImportResource::getUrl('view', ['record' => $import]));
            });
    }
}
