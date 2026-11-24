<?php

namespace Lunar\ProductImport\Filament\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Persisting\ImportResult;

/**
 * The share of product groups written while the import runs, then the share
 * of images downloaded.
 */
class ImportProgress extends Component
{
    #[Locked]
    public int $importId;

    public function mount(ProductImport $import): void
    {
        $this->importId = $import->id;
    }

    /**
     * Poll: tell the page once the import has finished.
     */
    public function poll(): void
    {
        if (ProductImport::query()->find($this->importId)?->status !== ImportStatus::Importing) {
            $this->dispatch('product-import-changed');
        }
    }

    public function render(): View
    {
        $import = ProductImport::query()->findOrFail($this->importId);

        return view('lunarpanel.product-import::livewire.import-progress', app(ImportResult::class)->progress($import));
    }
}
