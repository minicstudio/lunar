<div class="space-y-6">
  @if ($pruned)
  <x-filament::section>
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('lunarpanel.product-import::ui.result.pruned') }}</p>
  </x-filament::section>
  @endif

  <div class="grid gap-4 md:grid-cols-4">
    @foreach (['new_products', 'updated_products', 'new_variants', 'updated_variants', 'skipped_rows', 'failed_rows', 'created_brands', 'created_categories', 'created_discounts', 'discount_variants', 'associations', 'images_downloaded', 'images_failed'] as $key)
    <x-filament::section compact>
      <div class="text-sm text-gray-500">{{ __('lunarpanel.product-import::ui.result.stats.'.$key) }}</div>
      <div class="text-2xl font-semibold">{{ (int) ($stats[$key] ?? 0) }}</div>
    </x-filament::section>
    @endforeach
  </div>

  @foreach (['brands', 'categories', 'options', 'option_values', 'discounts'] as $type)
  @if (! empty($created[$type]))
  <x-filament::section :heading="__('lunarpanel.product-import::ui.result.created.'.$type)" compact>
    <p class="text-sm">{{ collect($created[$type])->pluck('name')->implode(', ') }}</p>
  </x-filament::section>
  @endif
  @endforeach

  <div class="flex gap-2">
    {{ $this->downloadReportAction }}
    {{ $this->retryAction }}
  </div>

  @if ($failedImages !== [])
  <x-filament::section :heading="__('lunarpanel.product-import::ui.result.failed_images')">
    <x-slot name="afterHeader">
      {{ $this->retryImagesAction }}
    </x-slot>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-gray-200 dark:border-white/10">
            <th class="py-2 pe-4">{{ __('lunarpanel.product-import::ui.result.image_product') }}</th>
            <th class="py-2 pe-4">{{ __('lunarpanel.product-import::ui.result.image_url') }}</th>
            <th class="py-2 pe-4">{{ __('lunarpanel.product-import::ui.result.image_reason') }}</th>
            <th class="py-2"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($failedImages as $image)
          <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="failed-image-{{ $image['id'] }}">
            <td class="py-2 pe-4">{{ $image['product'] }}</td>
            <td class="py-2 pe-4 break-all">{{ $image['url'] }}</td>
            <td class="py-2 pe-4 text-gray-600 dark:text-gray-300">{{ $image['reason'] }}</td>
            <td class="py-2 text-end">{{ ($this->correctImageUrlAction)(['image' => $image['id']]) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </x-filament::section>
  @endif

  @if (! $pruned)
  {{ $this->table }}
  @endif

  <x-filament-actions::modals />
</div>