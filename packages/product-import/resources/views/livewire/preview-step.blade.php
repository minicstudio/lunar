<div class="space-y-6">
  <div class="grid gap-4 md:grid-cols-6">
    @foreach (['products', 'new', 'updated', 'variants', 'warnings', 'errors'] as $key)
    <x-filament::section compact>
      <div class="text-sm text-gray-500">{{ __('lunarpanel.product-import::ui.preview.totals.'.$key) }}</div>
      <div class="text-2xl font-semibold" data-total="{{ $key }}">{{ $totals[$key] }}</div>
    </x-filament::section>
    @endforeach
  </div>

  <x-filament::tabs>
    @foreach ($filters as $key)
    <x-filament::tabs.item :active="$filter === $key" wire:click="setFilter('{{ $key }}')">
      {{ __('lunarpanel.product-import::ui.preview.filters.'.$key) }}
    </x-filament::tabs.item>
    @endforeach
  </x-filament::tabs>

  {{ $this->table }}

  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex gap-2">
      {{ $this->backToMappingAction }}
      {{ $this->backToValuesAction }}
    </div>
    <div class="flex gap-2">
      {{ $this->skipErrorsAction }}
      {{ $this->startAction }}
    </div>
  </div>

  <x-filament-actions::modals />
</div>