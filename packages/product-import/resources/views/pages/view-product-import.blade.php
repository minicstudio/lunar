<x-filament-panels::page>
  <div @if ($polls) wire:poll.3s="refreshImport" @endif class="space-y-6">
    <ol class="flex flex-wrap items-center gap-2 text-sm">
      @foreach ($steps as $step)
      <li class="flex items-center gap-2">
        <x-filament::badge :color="$step['color']">{{ $step['label'] }}</x-filament::badge>
        @if (! $loop->last)
        <span class="text-gray-400">›</span>
        @endif
      </li>
      @endforeach
    </ol>

    <div class="flex items-center gap-2 text-sm">
      <span>{{ __('lunarpanel.product-import::ui.list.status') }}:</span>
      <x-filament::badge :color="$import->status->color()">{{ $import->status->label() }}</x-filament::badge>
      @if ($import->failure_message)
      <span class="text-danger-600">{{ $import->failure_message }}</span>
      @endif
    </div>

    @if ($stepComponent)
    @livewire($stepComponent['class'], ['import' => $import], key($stepComponent['key']))
    @else
    <x-filament::section>
      {{ __('lunarpanel.product-import::ui.reading') }}
    </x-filament::section>
    @endif
  </div>
</x-filament-panels::page>
