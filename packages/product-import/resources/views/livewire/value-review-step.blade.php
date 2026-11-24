<div class="space-y-6">
  <div class="grid gap-4 md:grid-cols-5">
    @foreach (['existing', 'similar', 'new', 'without_category', 'properties_not_imported'] as $key)
    <x-filament::section compact>
      <div class="text-sm text-gray-500">{{ __('lunarpanel.product-import::ui.values.stats.'.$key) }}</div>
      <div class="text-2xl font-semibold">{{ $stats[$key] }}</div>
    </x-filament::section>
    @endforeach
  </div>

  <div class="flex flex-wrap items-center justify-between gap-4">
    <x-filament::tabs>
      @foreach ($types as $valueType)
      <x-filament::tabs.item :active="$type === $valueType->value" wire:click="setType('{{ $valueType->value }}')">
        {{ __('lunarpanel.product-import::ui.values.types.'.$valueType->value) }}
      </x-filament::tabs.item>
      @endforeach
    </x-filament::tabs>

    {{ $this->reviewOptionsAction }}
  </div>

  {{ $this->table }}

  <div class="flex flex-wrap items-center justify-between gap-4">
    {{ $this->backAction }}
    {{ $this->continueAction }}
  </div>

  <x-filament-actions::modals />
</div>