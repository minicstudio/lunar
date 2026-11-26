<div wire:poll.3s="poll">
  <x-filament::section>
    <div class="space-y-3">
      <p class="text-sm">{{ __('lunarpanel.product-import::ui.progress.'.($phase === 'images' ? 'images' : 'text'), ['processed' => $processed, 'total' =>
        $total, 'percent' => $percent]) }}</p>
      <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
        <div class="h-2 rounded-full bg-primary-500" style="width: {{ $percent }}%"></div>
      </div>
      <p class="text-xs text-gray-500">{{ __('lunarpanel.product-import::ui.progress.leave') }}</p>
    </div>
  </x-filament::section>
</div>