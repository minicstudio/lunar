<?php

namespace Lunar\ProductImport\Filament\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Lunar\Models\Product;
use Lunar\ProductImport\Actions\CorrectImageUrl;
use Lunar\ProductImport\Actions\RetryFailedImages;
use Lunar\ProductImport\Actions\RetryFailedRows;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Exports\ErrorReport;
use Lunar\ProductImport\Filament\Actions\CorrectRowAction;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;

/**
 * The outcome of a finished import: statistics, created records, failed rows
 * with corrections, the error report, and the retry of failed rows, and the
 * failed images with their reason, URL corrections, and their retry.
 */
class ImportResult extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $importId;

    public function mount(ProductImport $import): void
    {
        $this->importId = $import->id;
    }

    public function import(): ProductImport
    {
        return ProductImport::query()->findOrFail($this->importId);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ProductImportRow::includedIn($this->import())->where('status', RowStatus::Failed)->with('sheet'))
            ->heading(__('lunarpanel.product-import::ui.result.failed_rows'))
            ->defaultSort('row_number')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('lunarpanel.product-import::ui.preview.row'))
                    ->formatStateUsing(fn (ProductImportRow $record): string => $record->sheet->name.' · '.$record->row_number),
                TextColumn::make('match_value')
                    ->label(__('lunarpanel.product-import::ui.preview.key')),
                TextColumn::make('messages')
                    ->label(__('lunarpanel.product-import::ui.preview.messages'))
                    ->state(fn (ProductImportRow $record): string => collect($record->messages ?? [])->pluck('message')->implode(' · '))
                    ->wrap(),
            ])
            ->recordActions([
                CorrectRowAction::make()
                    ->visible(fn (): bool => ! $this->import()->isPruned()),
            ])
            ->paginated([25, 50]);
    }

    public function downloadReportAction(): Action
    {
        return Action::make('downloadReport')
            ->label(__('lunarpanel.product-import::ui.result.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => ! $this->import()->isPruned() && app(ErrorReport::class)->reportedRows($this->import())->exists())
            ->action(fn () => app(ErrorReport::class)->download($this->import()));
    }

    public function retryAction(): Action
    {
        return Action::make('retryFailed')
            ->label(__('lunarpanel.product-import::ui.result.retry'))
            ->visible(fn (): bool => app(RetryFailedRows::class)->canRetry($this->import()))
            ->requiresConfirmation()
            ->action(function (): void {
                try {
                    app(RetryFailedRows::class)->handle($this->import());
                } catch (ImportActionRefused $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $this->dispatch('product-import-changed');
            });
    }

    /**
     * Correct the URL of one failed image, given as the `image` argument.
     */
    public function correctImageUrlAction(): Action
    {
        return Action::make('correctImageUrl')
            ->label(__('lunarpanel.product-import::ui.result.correct_url'))
            ->link()
            ->fillForm(fn (array $arguments): array => ['url' => $this->failedImage($arguments)?->url])
            ->schema([
                TextInput::make('url')
                    ->label(__('lunarpanel.product-import::ui.result.image_url'))
                    ->required()
                    ->maxLength(2048),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $image = $this->failedImage($arguments);

                if ($image === null) {
                    return;
                }

                try {
                    app(CorrectImageUrl::class)->handle($image, (string) $data['url']);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    $action->halt();
                } catch (ImportActionRefused $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public function retryImagesAction(): Action
    {
        return Action::make('retryImages')
            ->label(__('lunarpanel.product-import::ui.result.retry_images'))
            ->visible(fn (): bool => app(RetryFailedImages::class)->canRetry($this->import()))
            ->requiresConfirmation()
            ->modalDescription(__('lunarpanel.product-import::ui.result.retry_images_confirm'))
            ->action(function (): void {
                try {
                    app(RetryFailedImages::class)->handle($this->import());
                } catch (ImportActionRefused $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $this->dispatch('product-import-changed');
            });
    }

    /**
     * The failed images with their product's name, while the rows are kept.
     *
     * @return list<array{id: int, product: string, url: string, reason: string}>
     */
    public function failedImages(ProductImport $import): array
    {
        if ($import->isPruned()) {
            return [];
        }

        $images = ProductImportImage::query()
            ->where('product_import_id', $import->id)
            ->where('status', ImageStatus::Failed)
            ->orderBy('product_id')
            ->orderBy('position')
            ->get();

        $products = Product::withTrashed()->whereIn('id', $images->pluck('product_id')->unique())->get()->keyBy('id');

        return $images->map(fn (ProductImportImage $image): array => [
            'id' => $image->id,
            'product' => '#'.$image->product_id.' '.($products->get($image->product_id)?->translateAttribute('name') ?? ''),
            'url' => $image->url,
            'reason' => (string) $image->error,
        ])->all();
    }

    public function render(): View
    {
        $import = $this->import();

        return view('lunarpanel.product-import::livewire.import-result', [
            'import' => $import,
            'stats' => $import->stats ?? [],
            'created' => $import->stats['created'] ?? [],
            'pruned' => $import->isPruned(),
            'failedImages' => $this->failedImages($import),
        ]);
    }

    /**
     * The failed image of the import that an action's arguments name.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function failedImage(array $arguments): ?ProductImportImage
    {
        return ProductImportImage::query()
            ->where('product_import_id', $this->importId)
            ->where('status', ImageStatus::Failed)
            ->find((int) ($arguments['image'] ?? 0));
    }
}
