<div class="space-y-6" @if ($busy) wire:poll.3s="loadState" @endif>
  @if ($busy)
  <x-filament::section>
    <div class="flex items-center gap-2 text-sm">
      <x-filament::loading-indicator class="h-5 w-5" />
      <span>{{ __('lunarpanel.product-import::ui.mapping.busy.' . $import->status->value) }}</span>
    </div>
  </x-filament::section>
  @endif

  <x-filament::tabs>
    @foreach ($tabs as $tab)
    <x-filament::tabs.item :active="$tab['active']" wire:click="selectSheet({{ $tab['id'] }})" :badge="$tab['badge']"
      :badge-color="$tab['badgeColor']">
      {{ $tab['name'] }}
    </x-filament::tabs.item>
    @endforeach
  </x-filament::tabs>

  @if ($activeSheet)
  <x-filament::section :heading="$activeSheet->name">
    <x-slot name="afterHeader">
      <div class="flex gap-2">
        @if ($activeSheet->skipped)
        @if ($activeSheet->row_count > 0)
        <x-filament::button size="sm" color="gray" wire:click="includeSheet({{ $activeSheet->id }})" :disabled="$busy">
          {{ __('lunarpanel.product-import::ui.mapping.include_sheet') }}
        </x-filament::button>
        @endif
        @else
        <x-filament::button size="sm" color="gray" wire:click="skipSheet({{ $activeSheet->id }})" :disabled="$busy">
          {{ __('lunarpanel.product-import::ui.mapping.skip_sheet') }}
        </x-filament::button>
        @endif
      </div>
    </x-slot>

    @if ($activeSheet->skipped)
    <p class="text-sm text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.sheet_skipped') }}</p>
    @else
    <div class="space-y-6">
      @if ($this->hasFailedAnalysis($activeSheet))
      <div
        class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm dark:border-danger-700 dark:bg-danger-950">
        <p class="font-medium text-danger-700 dark:text-danger-300">
          {{ $activeSheet->analysis_error['message'] ?? '' }}</p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
          {{ __('lunarpanel.product-import::ui.mapping.error_code') }}:
          <code>{{ $activeSheet->analysis_error['code'] ?? '' }}</code>
          ·
          {{ __('lunarpanel.product-import::ui.mapping.attempt', ['attempt' => $activeSheet->analysis_error['attempt']
          ?? $activeSheet->analysis_attempts]) }}
        </p>
        <div class="mt-3">
          <x-filament::button size="sm" color="danger" wire:click="retryAnalysis({{ $activeSheet->id }})"
            :disabled="$busy">
            {{ __('lunarpanel.product-import::ui.actions.retry') }}
          </x-filament::button>
        </div>
      </div>
      @endif

      @if ($this->hasOfferedProfile($activeSheet))
      <div
        class="rounded-lg border border-primary-300 bg-primary-50 p-4 text-sm dark:border-primary-700 dark:bg-primary-950">
        <p>{{ __('lunarpanel.product-import::ui.mapping.profile_offer', [
          'name' => $activeSheet->offeredProfile->name,
          'share' => (int) round(($activeSheet->offered_profile_score ?? 0) * 100),
          ]) }}
        </p>
        <div class="mt-3 flex gap-2">
          <x-filament::button size="sm" wire:click="applyProfile({{ $activeSheet->id }})">
            {{ __('lunarpanel.product-import::ui.mapping.apply_profile') }}
          </x-filament::button>
          <x-filament::button size="sm" color="gray" wire:click="dismissProfile({{ $activeSheet->id }})">
            {{ __('lunarpanel.product-import::ui.mapping.dismiss_profile') }}
          </x-filament::button>
        </div>
      </div>
      @endif

      @if ($this->isPending($activeSheet))
      <p class="text-sm text-gray-500">
        {{ __('lunarpanel.product-import::ui.mapping.analyzing') }}
      </p>
      @endif

      @if (isset($mappingErrors[$activeSheet->id]))
      <p class="text-sm text-danger-600">{{ $mappingErrors[$activeSheet->id] }}</p>
      @endif

      <div class="grid gap-4 md:grid-cols-3">
        <label class="block text-sm">
          <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.header_row') }}</span>
          <x-filament::input.wrapper class="mt-1">
            <x-filament::input.select wire:change="changeHeaderRow({{ $activeSheet->id }}, $event.target.value)"
              :disabled="$busy">
              @foreach ($this->headerRowChoices($activeSheet) as $rowNumber => $label)
              <option value="{{ $rowNumber }}" @selected($rowNumber===$activeSheet->header_row)>
                {{ $label }}
              </option>
              @endforeach
            </x-filament::input.select>
          </x-filament::input.wrapper>
        </label>

        <label class="block text-sm">
          <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.product_type') }}</span>
          <x-filament::input.wrapper class="mt-1">
            <x-filament::input.select wire:model.live="sheets.{{ $activeSheet->id }}.product_type_id" :disabled="$busy">
              <option value="">—</option>
              @foreach ($productTypes as $id => $name)
              <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </x-filament::input.select>
          </x-filament::input.wrapper>
        </label>

        <label class="block text-sm">
          <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.grouping') }}</span>
          <x-filament::input.wrapper class="mt-1">
            <x-filament::input.select wire:model.live="sheets.{{ $activeSheet->id }}.grouping" :disabled="$busy">
              @foreach ($groupings as $strategy)
              <option value="{{ $strategy }}">
                {{ __('lunarpanel.product-import::ui.mapping.groupings.' . $strategy) }}
              </option>
              @endforeach
            </x-filament::input.select>
          </x-filament::input.wrapper>
          <span class="mt-1 block text-xs text-gray-500">
            {{ __('lunarpanel.product-import::ui.mapping.grouping_help') }}
          </span>
        </label>

        @if ($this->mapsWeight($activeSheet->id))
        <label class="block text-sm">
          <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.weight_unit') }}</span>
          <x-filament::input.wrapper class="mt-1" :valid="filled($sheets[$activeSheet->id]['weight_unit'] ?? null)">
            <x-filament::input.select wire:model.live="sheets.{{ $activeSheet->id }}.weight_unit" :disabled="$busy">
              <option value="">
                {{ __('lunarpanel.product-import::ui.mapping.weight_unit_placeholder') }}
              </option>
              @foreach ($weightUnits as $unit)
              <option value="{{ $unit }}">{{ $unit }}</option>
              @endforeach
            </x-filament::input.select>
          </x-filament::input.wrapper>
          <span class="mt-1 block text-xs text-gray-500">
            {{ __('lunarpanel.product-import::ui.mapping.weight_unit_help') }}
          </span>
        </label>
        @endif

        @if ($this->mapsDimensions($activeSheet->id))
        <label class="block text-sm">
          <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.length_unit') }}</span>
          <x-filament::input.wrapper class="mt-1" :valid="filled($sheets[$activeSheet->id]['length_unit'] ?? null)">
            <x-filament::input.select wire:model.live="sheets.{{ $activeSheet->id }}.length_unit" :disabled="$busy">
              <option value="">
                {{ __('lunarpanel.product-import::ui.mapping.length_unit_placeholder') }}
              </option>
              @foreach ($lengthUnits as $unit)
              <option value="{{ $unit }}">{{ $unit }}</option>
              @endforeach
            </x-filament::input.select>
          </x-filament::input.wrapper>
          <span class="mt-1 block text-xs text-gray-500">
            {{ __('lunarpanel.product-import::ui.mapping.length_unit_help') }}
          </span>
        </label>
        @endif
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
              <th class="py-2 pe-2">{{ __('lunarpanel.product-import::ui.mapping.column') }}</th>
              <th class="py-2 pe-2">
                {{ __('lunarpanel.product-import::ui.mapping.samples') }}
              </th>
              <th class="py-2 pe-2">{{ __('lunarpanel.product-import::ui.mapping.target') }}</th>
              <th class="py-2 px-2 text-center">
                {{ __('lunarpanel.product-import::ui.mapping.confidence') }}
              </th>
              <th class="py-2"></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($columns as $column)
            <tr class="border-t border-gray-200 align-top dark:border-white/10"
              wire:key="column-{{ $activeSheet->id }}-{{ $column['index'] }}">
              <td class="py-4 pe-2">
                <div class="font-medium">{{ $column['header'] }}</div>
                <div class="text-xs text-gray-500">
                  {{ __('lunarpanel.product-import::ui.mapping.fill', ['share' => $column['fill']]) }}
                </div>
              </td>
              <td class="py-4 pe-2 text-xs text-gray-600 dark:text-gray-300">
                {{ $column['samples'] }}
              </td>
              <td class="py-4 pe-2">
                <div class="flex flex-col gap-2">
                  <x-filament::input.wrapper>
                    <x-filament::input.select
                      wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.target"
                      :disabled="$busy">
                      <option value="">
                        {{ __('lunarpanel.product-import::ui.mapping.unmapped') }}
                      </option>
                      @foreach ($targets as $definition)
                      <option value="{{ $definition->target }}">
                        {{ $definition->label }}</option>
                      @endforeach
                    </x-filament::input.select>
                  </x-filament::input.wrapper>

                  @if ($column['takesLocale'])
                  <x-filament::input.wrapper>
                    <x-filament::input.select
                      wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.locale"
                      :disabled="$busy">
                      @foreach ($languages as $language)
                      <option value="{{ $language }}">
                        {{ strtoupper($language) }}</option>
                      @endforeach
                    </x-filament::input.select>
                  </x-filament::input.wrapper>
                  @endif

                  @if ($column['isOption'])
                  <x-filament::input.wrapper>
                    <x-filament::input.select
                      wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.option"
                      :disabled="$busy">
                      <option value="new">
                        {{ __('lunarpanel.product-import::ui.mapping.new_option') }}
                      </option>
                      @foreach ($productOptions as $id => $name)
                      <option value="{{ $id }}">
                        {{ $name }}</option>
                      @endforeach
                    </x-filament::input.select>
                  </x-filament::input.wrapper>
                  @if ($column['needsOptionName'])
                  <x-filament::input.wrapper>
                    <x-filament::input type="text"
                      wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.option_name"
                      :placeholder="$column['header']" />
                  </x-filament::input.wrapper>
                  @endif
                  @endif

                  @if ($column['isPair'])
                  <label class="block text-xs" wire:key="pair-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.pair') }}</span>
                    <x-filament::input.wrapper>
                      <x-filament::input type="number" min="1"
                        wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.pair"
                        :disabled="$busy" />
                    </x-filament::input.wrapper>
                  </label>
                  @endif

                  @if ($column['format'] === 'number')
                  <div class="grid grid-cols-2 gap-1" wire:key="number-format-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <label class="block text-xs">
                      <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.decimal_separator') }}</span>
                      <x-filament::input.wrapper :valid="! $column['formatMissing'] && ! $column['flagged']">
                        <x-filament::input.select
                          wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.decimal"
                          :disabled="$busy">
                          <option value="">{{ __('lunarpanel.product-import::ui.mapping.format_placeholder') }}</option>
                          @foreach ($decimalSeparators as $separator)
                          <option value="{{ $separator }}">{{ __('lunarpanel.product-import::ui.mapping.separators.' . $separator) }}</option>
                          @endforeach
                        </x-filament::input.select>
                      </x-filament::input.wrapper>
                    </label>
                    <label class="block text-xs">
                      <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.thousands_separator') }}</span>
                      <x-filament::input.wrapper :valid="! $column['formatMissing'] && ! $column['flagged']">
                        <x-filament::input.select
                          wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.thousands"
                          :disabled="$busy">
                          <option value="">{{ __('lunarpanel.product-import::ui.mapping.format_placeholder') }}</option>
                          @foreach ($thousandsSeparators as $separator)
                          <option value="{{ $separator }}">{{ __('lunarpanel.product-import::ui.mapping.separators.' . $separator) }}</option>
                          @endforeach
                        </x-filament::input.select>
                      </x-filament::input.wrapper>
                    </label>
                  </div>
                  @elseif ($column['format'] === 'date')
                  <label class="block text-xs" wire:key="date-format-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.date_format') }}</span>
                    <x-filament::input.wrapper :valid="! $column['formatMissing'] && ! $column['flagged']">
                      <x-filament::input type="text"
                        wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.date_format"
                        list="date-formats-{{ $activeSheet->id }}" placeholder="Y-m-d" :disabled="$busy" />
                    </x-filament::input.wrapper>
                  </label>
                  @endif

                  @foreach ($column['separators'] as $kind => $separatorMissing)
                  <label class="block text-xs" wire:key="{{ $kind }}-separator-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.' . $kind . '_separator') }}</span>
                    <x-filament::input.wrapper :valid="! $separatorMissing && ! $column['flagged']">
                      <x-filament::input type="text"
                        wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.{{ $kind }}_separator"
                        list="separators-{{ $activeSheet->id }}"
                        :placeholder="__('lunarpanel.product-import::ui.mapping.separator_placeholder')" :disabled="$busy" />
                    </x-filament::input.wrapper>
                    <span class="mt-1 block text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.' . $kind . '_separator_help') }}</span>
                  </label>
                  @endforeach

                  @if ($column['meanings'] !== [])
                  <div class="space-y-1" wire:key="meanings-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="block text-xs text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.code_meanings') }}</span>
                    @foreach ($sheets[$activeSheet->id]['columns'][$column['index']]['values'] ?? [] as $position => $meaningRow)
                    <div class="flex items-center gap-1"
                      wire:key="meaning-{{ $activeSheet->id }}-{{ $column['index'] }}-{{ $position }}">
                      <x-filament::input.wrapper class="flex-1" :valid="! $column['flagged']">
                        <x-filament::input type="text"
                          wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.values.{{ $position }}.code"
                          :placeholder="__('lunarpanel.product-import::ui.mapping.code')" :disabled="$busy" />
                      </x-filament::input.wrapper>
                      <x-filament::input.wrapper class="flex-1" :valid="! $column['flagged']">
                        <x-filament::input.select
                          wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.values.{{ $position }}.meaning"
                          :disabled="$busy">
                          <option value="">{{ __('lunarpanel.product-import::ui.mapping.meaning') }}</option>
                          @foreach ($column['meanings'] as $meaning)
                          <option value="{{ $meaning }}">{{ __('lunarpanel.product-import::ui.mapping.meanings.' . $meaning) }}</option>
                          @endforeach
                        </x-filament::input.select>
                      </x-filament::input.wrapper>
                      <x-filament::link tag="button" size="sm" color="gray" :disabled="$busy"
                        wire:click="removeMeaning({{ $activeSheet->id }}, {{ $column['index'] }}, {{ $position }})">
                        {{ __('lunarpanel.product-import::ui.mapping.remove') }}
                      </x-filament::link>
                    </div>
                    @endforeach
                    <x-filament::link tag="button" size="sm" :disabled="$busy"
                      wire:click="addMeaning({{ $activeSheet->id }}, {{ $column['index'] }})">
                      {{ __('lunarpanel.product-import::ui.mapping.add_code') }}
                    </x-filament::link>
                  </div>
                  @endif

                  @if ($column['takesItemParts'])
                  <div class="space-y-1" wire:key="item-parts-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="block text-xs text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.item_parts') }}</span>
                    <div class="flex flex-wrap items-center gap-1">
                      @foreach ($sheets[$activeSheet->id]['columns'][$column['index']]['item_parts'] ?? [] as $position => $part)
                      <div class="flex items-center gap-1"
                        wire:key="item-part-{{ $activeSheet->id }}-{{ $column['index'] }}-{{ $position }}">
                        <x-filament::input.wrapper :valid="! $column['flagged']">
                          <x-filament::input.select
                            wire:model.live="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.item_parts.{{ $position }}"
                            :disabled="$busy">
                            @foreach ($itemParts as $itemPart)
                            <option value="{{ $itemPart }}">{{ __('lunarpanel.product-import::ui.mapping.parts.' . $itemPart) }}</option>
                            @endforeach
                          </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::link tag="button" size="sm" color="gray" :disabled="$busy"
                          wire:click="removeItemPart({{ $activeSheet->id }}, {{ $column['index'] }}, {{ $position }})">
                          {{ __('lunarpanel.product-import::ui.mapping.remove') }}
                        </x-filament::link>
                      </div>
                      @endforeach
                    </div>
                    <x-filament::link tag="button" size="sm" :disabled="$busy"
                      wire:click="addItemPart({{ $activeSheet->id }}, {{ $column['index'] }})">
                      {{ __('lunarpanel.product-import::ui.mapping.add_part') }}
                    </x-filament::link>
                    <span class="block text-xs text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.item_parts_help') }}</span>
                  </div>
                  @endif

                  @if ($column['takesImageSuffix'])
                  <label class="block text-xs" wire:key="image-suffix-{{ $activeSheet->id }}-{{ $column['index'] }}">
                    <span class="text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.image_suffix') }}</span>
                    <x-filament::input.wrapper :valid="! $column['flagged']">
                      <x-filament::input type="text"
                        wire:model.blur="sheets.{{ $activeSheet->id }}.columns.{{ $column['index'] }}.image_suffix"
                        placeholder="-small" :disabled="$busy" />
                    </x-filament::input.wrapper>
                    <span class="mt-1 block text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.image_suffix_help') }}</span>
                  </label>
                  @endif

                  @if ($column['format'] !== null && $column['flagged'])
                  <span class="text-xs text-warning-600">{{ __('lunarpanel.product-import::ui.mapping.format_check') }}</span>
                  @endif

                  @if ($column['suggestion'])
                  <span class="text-xs text-gray-500">{{ __('lunarpanel.product-import::ui.mapping.suggestion',
                    ['target' => $column['suggestion']]) }}</span>
                  @endif
                </div>
              </td>
              <td class="py-4 px-2 text-center">
                @if ($column['confidence'] !== null)
                <x-filament::badge :color="$column['confidenceColor']">
                  {{ $column['confidence'] }}%
                </x-filament::badge>
                @if ($column['flagged'])
                <span class="mt-1 block text-xs text-warning-600">{{ __('lunarpanel.product-import::ui.mapping.check')
                  }}</span>
                @endif
                @endif
              </td>
              <td class="py-4 text-end">
                @if ($column['isIgnored'])
                <x-filament::link tag="button" size="sm"
                  wire:click="restoreColumn({{ $activeSheet->id }}, {{ $column['index'] }})">{{
                  __('lunarpanel.product-import::ui.mapping.restore') }}</x-filament::link>
                @else
                <x-filament::link tag="button" size="sm" color="gray"
                  wire:click="ignoreColumn({{ $activeSheet->id }}, {{ $column['index'] }})">{{
                  __('lunarpanel.product-import::ui.mapping.ignore') }}</x-filament::link>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        <datalist id="date-formats-{{ $activeSheet->id }}">
          @foreach ($dateFormats as $dateFormat)
          <option value="{{ $dateFormat }}"></option>
          @endforeach
        </datalist>
        <datalist id="separators-{{ $activeSheet->id }}">
          @foreach ($separators as $separator)
          <option value="{{ $separator }}"></option>
          @endforeach
        </datalist>
      </div>
    </div>
    @endif
  </x-filament::section>
  @endif

  <x-filament::section :heading="__('lunarpanel.product-import::ui.mapping.options')">
    <div class="grid gap-4 md:grid-cols-3 text-sm">
      <label class="block">
        <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.match_key') }}</span>
        <x-filament::input.wrapper class="mt-1">
          <x-filament::input.select wire:model.live="options.match_key" :disabled="$busy">
            <option value="sku">SKU</option>
            <option value="ean">EAN</option>
          </x-filament::input.select>
        </x-filament::input.wrapper>
      </label>
      <label class="block">
        <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.existing_variants') }}</span>
        <x-filament::input.wrapper class="mt-1">
          <x-filament::input.select wire:model.live="options.existing_variants" :disabled="$busy">
            <option value="update">{{ __('lunarpanel.product-import::ui.mapping.existing.update') }}
            </option>
            <option value="skip">{{ __('lunarpanel.product-import::ui.mapping.existing.skip') }}
            </option>
          </x-filament::input.select>
        </x-filament::input.wrapper>
      </label>
      <label class="block">
        <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.new_status') }}</span>
        <x-filament::input.wrapper class="mt-1">
          <x-filament::input.select wire:model.live="options.new_product_status" :disabled="$busy">
            <option value="draft">{{ __('lunarpanel.product-import::ui.mapping.status.draft') }}</option>
            <option value="published">{{ __('lunarpanel.product-import::ui.mapping.status.published') }}
            </option>
            <option value="from_column">
              {{ __('lunarpanel.product-import::ui.mapping.status.from_column') }}</option>
          </x-filament::input.select>
        </x-filament::input.wrapper>
      </label>
      <label class="block">
        <span class="font-medium">{{ __('lunarpanel.product-import::ui.mapping.collection_group') }}</span>
        <x-filament::input.wrapper class="mt-1">
          <x-filament::input.select wire:model.live="options.collection_group_id" :disabled="$busy">
            @foreach ($collectionGroups as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
          </x-filament::input.select>
        </x-filament::input.wrapper>
      </label>
    </div>

    <div class="mt-4">
      {{ $this->optionsForm }}
    </div>
  </x-filament::section>

  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>{{ $this->saveProfileAction }}</div>
    <div class="flex items-center gap-3">
      @if ($blocker = $this->blocker())
      <span class="text-sm text-gray-500">{{ $blocker }}</span>
      @endif
      {{ $this->continueAction }}
    </div>
  </div>

  <x-filament-actions::modals />
</div>
