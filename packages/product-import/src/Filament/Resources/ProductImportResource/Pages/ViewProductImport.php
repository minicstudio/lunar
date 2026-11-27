<?php

namespace Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Livewire\Component;
use Lunar\ProductImport\Actions\CancelImport;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Filament\Livewire\ImportProgress;
use Lunar\ProductImport\Filament\Livewire\ImportResult;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Filament\Livewire\SheetMappingStep;
use Lunar\ProductImport\Filament\Livewire\ValueReviewStep;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Models\ProductImport;

/**
 * One import: a step indicator and the step that matches its status.
 *
 * @property ProductImport $record
 */
class ViewProductImport extends Page
{
    use InteractsWithRecord;

    /**
     * The resource class for the import page.
     */
    protected static string $resource = ProductImportResource::class;

    protected string $view = 'lunarpanel.product-import::pages.view-product-import';

    /**
     * The steps of the indicator.
     *
     * @var list<string>
     */
    public const STEPS = ['upload', 'mapping', 'values', 'preview', 'import'];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->record), 403);
    }

    public function getTitle(): string
    {
        return $this->record->fileLabel();
    }

    public function getSubheading(): ?string
    {
        return app(AiAvailability::class)->label();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * The step the import is at.
     */
    public function currentStep(): string
    {
        return match ($this->record->status) {
            ImportStatus::Uploaded => 'upload',
            ImportStatus::Analyzing, ImportStatus::AnalysisFailed, ImportStatus::AwaitingMapping, ImportStatus::Staging => 'mapping',
            ImportStatus::ReviewingValues => 'values',
            ImportStatus::PreviewReady => 'preview',
            default => 'import',
        };
    }

    /**
     * The Livewire component of the step the import's status shows, with its
     * key; null while the file is read. The mapping and the result restart
     * with each status, so their key holds it.
     *
     * @return array{class: class-string<Component>, key: string}|null
     */
    protected function stepComponent(): ?array
    {
        $id = $this->record->id;
        $status = $this->record->status;

        return match ($status) {
            ImportStatus::Uploaded => null,
            ImportStatus::Analyzing, ImportStatus::AnalysisFailed, ImportStatus::AwaitingMapping, ImportStatus::Staging => ['class' => SheetMappingStep::class, 'key' => 'mapping-'.$id.'-'.$status->value],
            ImportStatus::ReviewingValues => ['class' => ValueReviewStep::class, 'key' => 'values-'.$id],
            ImportStatus::PreviewReady => ['class' => PreviewStep::class, 'key' => 'preview-'.$id],
            ImportStatus::Importing => ['class' => ImportProgress::class, 'key' => 'progress-'.$id],
            default => ['class' => ImportResult::class, 'key' => 'result-'.$id.'-'.$status->value],
        };
    }

    /**
     * Refresh the record, for the step components after a change.
     */
    public function refreshImport(): void
    {
        $this->record->refresh();
    }

    protected function getListeners(): array
    {
        return ['product-import-changed' => 'refreshImport'];
    }

    /**
     * The step indicator with each step's badge color, whether the page polls
     * (while the file is read or a job runs), and the step component.
     *
     * @return array{import: ProductImport, steps: list<array{label: string, color: string}>, polls: bool, stepComponent: array{class: class-string<Component>, key: string}|null}
     */
    protected function getViewData(): array
    {
        $status = $this->record->status;
        $current = array_search($this->currentStep(), self::STEPS, true);
        $steps = [];

        foreach (self::STEPS as $index => $step) {
            $steps[] = [
                'label' => ($index + 1).'. '.__('lunarpanel.product-import::ui.steps.'.$step),
                'color' => $index < $current ? 'success' : ($index === $current ? 'primary' : 'gray'),
            ];
        }

        return [
            'import' => $this->record,
            'steps' => $steps,
            'polls' => $status->isBusy() || $status === ImportStatus::Uploaded,
            'stepComponent' => $this->stepComponent(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancelImport')
                ->label(__('lunarpanel.product-import::ui.actions.cancel'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('lunarpanel.product-import::ui.cancel.confirm'))
                ->visible(fn (): bool => $this->record->status->isCancellable())
                ->action(function (): void {
                    try {
                        app(CancelImport::class)->handle($this->record);
                    } catch (ImportActionRefused $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    $this->record->refresh();
                }),
        ];
    }
}
