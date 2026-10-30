<?php

namespace Lunar\Admin\Support\Actions\Orders;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Lunar\Actions\Orders\GetOrderSplitGates;
use Lunar\Actions\Orders\SplitOrder;
use Lunar\Actions\Orders\SplitOrderPreview;
use Lunar\Actions\Orders\SplitOrderPreviewTarget;
use Lunar\Actions\Orders\SplitOrderRequest;
use Lunar\Actions\Orders\SplitOrderTarget;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Exceptions\Orders\InvalidOrderSplitException;
use Lunar\Exceptions\Orders\LockedOrderSplitException;
use Lunar\Exceptions\Orders\OrderSplitException;
use Lunar\Exceptions\Orders\UnauthorizedOrderSplitException;
use Lunar\Loyalty\Exceptions\LoyaltySplitRejectedException;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Throwable;

class SplitOrderAction extends Action
{
    /**
     * @var array<string, SplitOrderPreview|null>
     */
    private static array $previews = [];

    /**
     * @var array<string, int|null>
     */
    private array $catalogPrices = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('lunarpanel::order.action.split_order.label'));
        $this->modalHeading(__('lunarpanel::order.action.split_order.heading'));
        $this->modalSubmitActionLabel(__('lunarpanel::order.action.split_order.confirm'));
        $this->modalWidth(Width::SevenExtraLarge);

        $this->visible(fn (?Order $record): bool => $record instanceof Order
            && UpdateOrderProductLines::userCanManageOrders()
            && GetOrderSplitGates::run($record)->passes()
            && $this->orderHasMoreThanOneUnit($record));

        $this->fillForm(fn (Order $record): array => $this->initialState($record));

        $this->schema(fn (Order $record): array => [
            Callout::make(__('lunarpanel::order.action.split_order.helper'))
                ->info()
                ->columnSpanFull(),
            Callout::make(__('lunarpanel::order.infolist.alert.split_erp_warning'))
                ->warning()
                ->visible(fn (): bool => GetOrderSplitGates::run($record)->erpWarning)
                ->columnSpanFull(),
            Grid::make(['default' => 2])->schema([
                Section::make(__('lunarpanel::order.action.split_order.products'))
                    ->columnSpan(['default' => 1])
                    ->compact()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemHeaders(false)
                            ->compact()
                            ->columns(1)
                            ->extraAttributes(['class' => 'split-order-lines'])
                            ->afterStateUpdated(function () use ($record): void {
                                $this->fillCalculatedShipping($record);
                            })
                            ->schema([
                                Hidden::make('order_line_id'),
                                Hidden::make('quantity'),
                                Hidden::make('description'),
                                Placeholder::make('line_name')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): string => trim((string) $get('description').' × '.$get('quantity'))),
                                Select::make('target')
                                    ->label(__('lunarpanel::order.action.split_order.move_to_order'))
                                    ->options(fn (Get $get): array => $this->targetOptions($get('../../orders')))
                                    ->visible(fn (Get $get): bool => (int) $get('quantity') <= 1 || ! $get('divided'))
                                    ->required(fn (Get $get): bool => (int) $get('quantity') <= 1 || ! $get('divided'))
                                    ->live(),
                                Toggle::make('divided')
                                    ->label(__('lunarpanel::order.action.split_order.divide_quantity'))
                                    ->visible(fn (Get $get): bool => (int) $get('quantity') > 1)
                                    ->columnSpanFull()
                                    ->live(),
                                ToggleButtons::make('price_source')
                                    ->label(__('lunarpanel::order.action.split_order.price'))
                                    ->aboveContent(
                                        Text::make(__('lunarpanel::order.action.split_order.price_changed'))
                                            ->size(TextSize::ExtraSmall)
                                            ->extraAttributes(['class' => 'split-price-note']),
                                    )
                                    ->options([
                                        'original' => __('lunarpanel::order.action.split_order.original_price'),
                                        'current' => __('lunarpanel::order.action.split_order.current_price'),
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->size(Size::ExtraSmall)
                                    ->extraAttributes(['class' => 'split-price-toggle'])
                                    ->default('original')
                                    ->visible(fn (Get $get): bool => $this->priceChoiceVisible($record, $get))
                                    ->columnSpanFull()
                                    ->live(),
                                Repeater::make('parts')
                                    ->hiddenLabel()
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => (int) $get('quantity') > 1 && (bool) $get('divided'))
                                    ->schema([
                                        Select::make('target')
                                            ->label(__('lunarpanel::order.action.split_order.move_to_order'))
                                            ->options(fn (Get $get): array => $this->targetOptions($get('../../../../orders')))
                                            ->live(),
                                        TextInput::make('quantity')
                                            ->label(__('lunarpanel::order.infolist.quantity.label'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->live(),
                                    ]),
                            ]),
                    ]),
                Section::make(__('lunarpanel::order.action.split_order.preview'))
                    ->columnSpan(['default' => 1])
                    ->compact()
                    ->schema([
                        Select::make('coupon')
                            ->label(__('lunarpanel::order.action.split_order.coupon'))
                            ->options(fn (Get $get): array => $this->choiceOptions($this->targetOptions($get('orders'))))
                            ->visible(fn (): bool => filled($this->couponCode($record)))
                            ->required(fn (): bool => filled($this->couponCode($record)))
                            ->live(),
                        Select::make('loyalty')
                            ->label(__('lunarpanel::order.action.split_order.loyalty'))
                            ->options(fn (Get $get): array => $this->choiceOptions($this->targetOptions($get('orders'))))
                            ->visible(fn (): bool => (int) data_get($record->meta, 'loyalty_points_to_redeem') > 0)
                            ->required(fn (): bool => (int) data_get($record->meta, 'loyalty_points_to_redeem') > 0)
                            ->live(),
                        Repeater::make('orders')
                            ->hiddenLabel()
                            ->minItems(2)
                            ->reorderable(false)
                            ->addActionLabel(__('lunarpanel::order.action.split_order.new_order'))
                            ->itemLabel(fn (?int $index): string => __('lunarpanel::order.action.split_order.new_order').' '.(($index ?? 0) + 1))
                            ->deletable(function (Repeater $component): bool {
                                $state = $component->getRawState();

                                return is_array($state) && count($state) > 2;
                            })
                            ->live()
                            ->schema([
                                Hidden::make('key')
                                    ->default(fn (): string => (string) Str::uuid()),
                                Hidden::make('shipping_custom')
                                    ->default(false),
                                Placeholder::make('preview')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): HtmlString => $this->previewContent($record, $get)),
                                TextInput::make('shipping')
                                    ->label(__('lunarpanel::order.action.split_order.shipping'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (): string => $record->currency?->code ?? '')
                                    ->helperText(fn (Get $get): ?string => $this->shippingHelper($record, $get))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('shipping_custom', true);
                                    }),
                                Select::make('payment_option')
                                    ->label(__('lunarpanel::order.action.split_order.payment_method'))
                                    ->options($this->paymentOptions())
                                    ->required()
                                    ->live(),
                            ]),
                    ]),
            ]),
            Callout::make(function () use ($record): HtmlString {
                $messages = collect($this->blockerMessages($record, $this->mountedData()))
                    ->map(fn (string $message): string => '<span class="block">'.e($message).'</span>')
                    ->implode('');

                return new HtmlString($messages);
            })
                ->warning()
                ->visible(fn (): bool => $this->confirmBlockers($record, $this->mountedData()) !== [])
                ->columnSpanFull(),
        ]);

        $this->modalSubmitAction(function (Action $action): Action {
            return $action->disabled(function (): bool {
                $record = $this->getRecord();

                if (! $record instanceof Order) {
                    return true;
                }

                return $this->confirmBlockers($record, $this->mountedData()) !== [];
            });
        });

        $this->action(function (Order $record, array $data, Action $action): void {
            $blockers = $this->confirmBlockers($record, $data);

            if ($blockers !== []) {
                Notification::make()
                    ->title($this->blockerMessage($blockers[0]))
                    ->danger()
                    ->send();
                $action->halt();

                return;
            }

            try {
                SplitOrder::run($record, $this->requestFromState($record, $data));
            } catch (LoyaltySplitRejectedException $exception) {
                Notification::make()
                    ->title($exception->getMessage())
                    ->warning()
                    ->send();
                $action->halt();

                return;
            } catch (OrderSplitException $exception) {
                Notification::make()
                    ->title($this->failureTitle($exception))
                    ->danger()
                    ->send();
                $action->halt();

                return;
            } catch (Throwable $exception) {
                report($exception);
                Notification::make()
                    ->title($exception->getMessage())
                    ->danger()
                    ->send();
                $action->halt();

                return;
            }

            Notification::make()
                ->title(__('lunarpanel::order.action.split_order.notification'))
                ->success()
                ->send();

            $livewire = $this->getLivewire();

            if (isset($livewire->record) && $livewire->record instanceof Order) {
                $livewire->record->refresh();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function initialState(Order $order): array
    {
        $payment = (string) (data_get($order->meta, 'payment_option') ?: 'cash-on-delivery');
        $lines = $order->productLines()->with('purchasable')->orderBy('id')->get()->values();
        $orders = [
            ['key' => '1', 'shipping' => null, 'payment_option' => $payment],
            ['key' => '2', 'shipping' => null, 'payment_option' => $payment],
        ];
        $separatePreorders = $lines->contains(fn (OrderLine $line): bool => $this->lineIsPreorder($line))
            && $lines->contains(fn (OrderLine $line): bool => ! $this->lineIsPreorder($line));
        $lineState = [];

        foreach ($lines as $index => $line) {
            $lineState[] = [
                'order_line_id' => $line->id,
                'quantity' => $line->quantity,
                'description' => $line->description,
                'divided' => false,
                'price_source' => 'original',
                'target' => $separatePreorders
                    ? ($this->lineIsPreorder($line) ? '1' : '2')
                    : (($index % 2) === 0 ? '1' : '2'),
                'parts' => [
                    ['target' => '1', 'quantity' => 0],
                    ['target' => '2', 'quantity' => 0],
                ],
            ];
        }

        $state = [
            'orders' => $orders,
            'lines' => $lineState,
            'coupon' => filled($this->couponCode($order)) ? '1' : 'none',
            'loyalty' => (int) data_get($order->meta, 'loyalty_points_to_redeem') > 0 ? '1' : 'none',
        ];

        $preview = $this->rememberPreview($order, $state);

        if ($preview instanceof SplitOrderPreview) {
            foreach ($preview->targets as $target) {
                foreach ($state['orders'] as $index => $orderState) {
                    if ((string) ($orderState['key'] ?? '') !== $target->key) {
                        continue;
                    }

                    $state['orders'][$index]['shipping'] = $this->decimalFromMinor($order, $target->shippingMinor);
                }
            }
        }

        return $state;
    }

    private function lineIsPreorder(OrderLine $line): bool
    {
        return $line->purchasable_type === ProductVariant::morphName()
            && $line->purchasable?->purchasable === 'preorder';
    }

    /**
     * One unit cannot be placed on two orders.
     */
    private function orderHasMoreThanOneUnit(Order $order): bool
    {
        return (int) $order->productLines()->sum('quantity') > 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestFromState(Order $order, array $data): SplitOrderRequest
    {
        $quantities = $this->quantitiesByTarget($data);
        $targets = [];

        foreach (array_values($data['orders'] ?? []) as $orderState) {
            $key = (string) ($orderState['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $targets[] = new SplitOrderTarget(
                key: $key,
                quantities: $quantities[$key] ?? [],
                shippingMinor: $this->minorFromDecimal($order, $orderState['shipping'] ?? null),
                paymentOption: (string) ($orderState['payment_option'] ?? ''),
                paymentType: $this->paymentType($order, (string) ($orderState['payment_option'] ?? '')),
            );
        }

        $nonEmpty = $this->nonEmptyKeys($quantities);
        $coupon = (string) ($data['coupon'] ?? 'none');
        $loyalty = (string) ($data['loyalty'] ?? 'none');
        $applyCoupon = filled($this->couponCode($order)) && $coupon !== 'none' && $coupon !== '';
        $applyLoyalty = (int) data_get($order->meta, 'loyalty_points_to_redeem') > 0
            && $loyalty !== 'none'
            && $loyalty !== '';
        $catalogPricedLineIds = [];

        foreach (array_values(is_array($data['lines'] ?? null) ? $data['lines'] : []) as $line) {
            if (! is_array($line) || ($line['price_source'] ?? 'original') !== 'current') {
                continue;
            }

            $lineId = (int) ($line['order_line_id'] ?? 0);

            if ($lineId !== 0) {
                $catalogPricedLineIds[] = $lineId;
            }
        }

        return new SplitOrderRequest(
            targets: $targets,
            couponTarget: $applyCoupon ? $coupon : null,
            applyCoupon: $applyCoupon,
            loyaltyTarget: $applyLoyalty ? $loyalty : null,
            applyLoyalty: $applyLoyalty,
            catalogPricedLineIds: $catalogPricedLineIds,
        );
    }

    /**
     * Reasons the confirm button stays disabled. An empty list means the split can be confirmed.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function confirmBlockers(Order $order, array $data): array
    {
        if ($data === []) {
            return ['invalid'];
        }

        $blockers = [];
        $orders = array_values(is_array($data['orders'] ?? null) ? $data['orders'] : []);

        if (count($orders) < 2) {
            $blockers[] = 'too_few_targets';
        }

        $keys = [];

        foreach ($orders as $orderState) {
            if (! is_array($orderState)) {
                $blockers[] = 'lines_mismatch';

                continue;
            }

            $key = (string) ($orderState['key'] ?? '');
            $shipping = $this->minorFromDecimal($order, $orderState['shipping'] ?? null);

            if ($key === '' || isset($keys[$key])) {
                $blockers[] = 'lines_mismatch';
            }

            if ($key !== '') {
                $keys[$key] = true;
            }

            if ($shipping === null) {
                $blockers[] = 'shipping_required';
            } elseif ($shipping < 0) {
                $blockers[] = 'negative_shipping';
            }

            if (blank($orderState['payment_option'] ?? null)) {
                $blockers[] = 'payment_required';
            }
        }

        $quantities = $this->quantitiesByTarget($data);
        $assigned = [];

        foreach ($quantities as $lineQuantities) {
            foreach ($lineQuantities as $lineId => $quantity) {
                $assigned[$lineId] = ($assigned[$lineId] ?? 0) + $quantity;
            }
        }

        $lines = $order->productLines()->get();

        if ($lines->isEmpty()) {
            $blockers[] = 'lines_mismatch';
        }

        $unassigned = false;
        $assignedTwice = false;

        foreach ($lines as $line) {
            $sum = $assigned[$line->id] ?? 0;

            if ($sum < (int) $line->quantity) {
                $unassigned = true;
            } elseif ($sum > (int) $line->quantity) {
                $assignedTwice = true;
            }
        }

        if ($unassigned) {
            $blockers[] = 'unassigned';
        }

        if ($assignedTwice) {
            $blockers[] = 'assigned_twice';
        }

        $nonEmpty = $this->nonEmptyKeys($quantities);

        if (count($nonEmpty) < 2) {
            $blockers[] = 'too_few_targets';
        }

        if (filled($this->couponCode($order))) {
            $coupon = (string) ($data['coupon'] ?? '');

            if ($coupon === '' || ($coupon !== 'none' && ! in_array($coupon, $nonEmpty, true))) {
                $blockers[] = 'coupon';
            }
        }

        if ((int) data_get($order->meta, 'loyalty_points_to_redeem') > 0) {
            $loyalty = (string) ($data['loyalty'] ?? '');

            if ($loyalty === '' || ($loyalty !== 'none' && ! in_array($loyalty, $nonEmpty, true))) {
                $blockers[] = 'loyalty';
            } elseif ($loyalty === 'none' && $this->loyaltySpendExists($order)) {
                $blockers[] = 'loyalty_spent';
            } elseif ($loyalty !== 'none' && $this->loyaltySpendExceedsTarget($order, $data, $loyalty)) {
                $blockers[] = 'loyalty_cannot_accept';
            }
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function blockerMessages(Order $order, array $data): array
    {
        return array_map(
            fn (string $code): string => $this->blockerMessage($code),
            $this->confirmBlockers($order, $data),
        );
    }

    private function blockerMessage(string $code): string
    {
        if ($code === 'invalid') {
            return __('lunarpanel::order.action.split_order.invalid');
        }

        $key = 'lunarpanel::order.action.split_order.gates.'.$code;
        $translated = __($key);

        return $translated === $key
            ? __('lunarpanel::order.action.split_order.invalid')
            : $translated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, int>>
     */
    private function quantitiesByTarget(array $data): array
    {
        $quantities = [];

        foreach (array_values($data['lines'] ?? []) as $line) {
            $lineId = (int) ($line['order_line_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($lineId === 0) {
                continue;
            }

            if ($quantity > 1 && ! empty($line['divided'])) {
                foreach (array_values($line['parts'] ?? []) as $part) {
                    $key = (string) ($part['target'] ?? '');
                    $partQuantity = (int) ($part['quantity'] ?? 0);

                    if ($key === '' || $partQuantity <= 0) {
                        continue;
                    }

                    $quantities[$key][$lineId] = ($quantities[$key][$lineId] ?? 0) + $partQuantity;
                }

                continue;
            }

            $key = (string) ($line['target'] ?? '');

            if ($key === '' || $quantity <= 0) {
                continue;
            }

            $quantities[$key][$lineId] = ($quantities[$key][$lineId] ?? 0) + $quantity;
        }

        return $quantities;
    }

    /**
     * @param  array<string, array<int, int>>  $quantities
     * @return list<string>
     */
    private function nonEmptyKeys(array $quantities): array
    {
        $keys = [];

        foreach ($quantities as $key => $lineQuantities) {
            if (array_sum($lineQuantities) > 0) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * Keep the order keys. Unpacking this array would renumber 1 and 2 to 0 and 1,
     * so choosing "New order 1" would be stored as an order that does not exist.
     *
     * @param  array<int|string, string>  $targets
     * @return array<int|string, string>
     */
    private function choiceOptions(array $targets): array
    {
        $options = [
            'none' => __('lunarpanel::order.action.split_order.do_not_apply'),
        ];

        foreach ($targets as $key => $label) {
            $options[$key] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function targetOptions(mixed $orders): array
    {
        $options = [];

        foreach (array_values(is_array($orders) ? $orders : []) as $index => $orderState) {
            $key = (string) ($orderState['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $options[$key] = __('lunarpanel::order.action.split_order.new_order').' '.($index + 1);
        }

        return $options;
    }

    private function previewContent(Order $order, Get $get): HtmlString
    {
        $key = (string) $get('key');
        $data = $this->mountedData();
        $preview = $data === [] ? null : $this->rememberPreview($order, $data);
        $target = collect($preview?->targets ?? [])->first(
            fn (SplitOrderPreviewTarget $item): bool => $item->key === $key,
        );

        $blocks = [];
        $total = null;
        $names = [];

        foreach (array_values($data['lines'] ?? []) as $line) {
            $lineId = (int) ($line['order_line_id'] ?? 0);

            if ($lineId !== 0) {
                $names[$lineId] = (string) ($line['description'] ?? '');
            }
        }

        if ($target instanceof SplitOrderPreviewTarget && $target->lines !== []) {
            foreach ($target->lines as $line) {
                $lineId = (int) ($line['order_line_id'] ?? 0);
                $name = $names[$lineId] ?? (string) $line['description'];
                $details = [[
                    'label' => e(__('lunarpanel::order.infolist.unit_price.label')),
                    'amount' => e($this->moneyLabel($order, (int) $line['unit_price'])),
                ]];
                $discount = (int) ($line['discount'] ?? 0);
                $couponDiscount = min($discount, max(0, (int) ($line['coupon_discount'] ?? 0)));
                $productDiscount = $discount - $couponDiscount;

                if ($productDiscount > 0) {
                    $details[] = [
                        'label' => e(__('lunarpanel::order.infolist.discount_breakdown.discount')),
                        'amount' => e($this->moneyLabel($order, -$productDiscount)),
                    ];
                }

                if ($couponDiscount > 0) {
                    $couponCode = (string) ($line['coupon_code'] ?? '');
                    $couponLabel = $couponCode !== ''
                        ? __('lunarpanel::order.infolist.discount_breakdown.coupon_with_name', ['name' => $couponCode])
                        : __('lunarpanel::order.infolist.discount_breakdown.coupon');

                    $details[] = [
                        'label' => e($couponLabel),
                        'amount' => e($this->moneyLabel($order, -$couponDiscount)),
                    ];
                }

                $blocks[] = [
                    'label' => e($name.' × '.$line['quantity']),
                    'amount' => e($this->moneyLabel($order, (int) $line['sub_total'])),
                    'details' => $details,
                ];
            }

            $total = e($this->moneyLabel($order, (int) $target->total));
        } else {
            foreach ($this->assignedLineLabels($data, $key) as $label) {
                $blocks[] = [
                    'label' => e($label),
                    'amount' => '',
                    'details' => [],
                ];
            }
        }

        if ($blocks === []) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">'.e(__('lunarpanel::order.action.split_order.empty_preview')).'</p>',
            );
        }

        $html = '<div class="text-sm">';

        foreach ($blocks as $block) {
            $html .= '<div style="margin-bottom: 8px">';
            $html .= '<div class="flex items-baseline justify-between gap-x-4"><span>'.$block['label'].'</span><span>'.$block['amount'].'</span></div>';

            foreach ($block['details'] as $detail) {
                $html .= '<div class="flex items-baseline justify-between gap-x-4 ps-3 text-gray-500 dark:text-gray-400"><span>'
                    .$detail['label'].'</span><span>'.$detail['amount'].'</span></div>';
            }

            $html .= '</div>';
        }

        if ($total !== null && $target instanceof SplitOrderPreviewTarget) {
            $html .= '<div class="flex items-baseline justify-between gap-x-4" style="margin-bottom: 8px"><span>'
                .e(__('lunarpanel::order.action.split_order.shipping'))
                .'</span><span>'.e($this->moneyLabel($order, (int) $target->shippingMinor)).'</span></div>';
            $html .= '<div class="flex items-baseline justify-between gap-x-4 font-semibold" style="font-size: 1rem"><span>'
                .e(__('lunarpanel::order.action.split_order.order_total'))
                .'</span><span>'.$total.'</span></div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function assignedLineLabels(array $data, string $key): array
    {
        if ($key === '') {
            return [];
        }

        $labels = [];

        foreach (array_values($data['lines'] ?? []) as $line) {
            $description = (string) ($line['description'] ?? '');
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity > 1 && ! empty($line['divided'])) {
                foreach (array_values($line['parts'] ?? []) as $part) {
                    $partQuantity = (int) ($part['quantity'] ?? 0);

                    if ((string) ($part['target'] ?? '') === $key && $partQuantity > 0) {
                        $labels[] = $description.' × '.$partQuantity;
                    }
                }

                continue;
            }

            if ((string) ($line['target'] ?? '') === $key && $quantity > 0) {
                $labels[] = $description.' × '.$quantity;
            }
        }

        return $labels;
    }

    private function priceChoiceVisible(Order $order, Get $get): bool
    {
        $line = $order->productLines->firstWhere('id', (int) $get('order_line_id'));

        if (! $line instanceof OrderLine) {
            return false;
        }

        $stored = $this->storedUnitPrice($line);
        $quantities = $this->assignedQuantities($get);

        if ($quantities === []) {
            $quantities = [(int) $line->quantity];
        }

        foreach ($quantities as $quantity) {
            $catalog = $this->catalogUnitPrice($order, $line, $quantity);

            if ($catalog !== null && $catalog !== $stored) {
                return true;
            }
        }

        return false;
    }

    private function storedUnitPrice(OrderLine $line): int
    {
        $price = $line->unit_price;

        return (int) round(
            $price->unitDecimal(false) * (int) $price->currency->factor,
            (int) $price->currency->decimal_places
        );
    }

    /**
     * @return list<int>
     */
    private function assignedQuantities(Get $get): array
    {
        $quantity = (int) $get('quantity');

        if ($quantity > 1 && $get('divided')) {
            $quantities = [];

            foreach (array_values(is_array($get('parts')) ? $get('parts') : []) as $part) {
                if (! is_array($part)) {
                    continue;
                }

                $partQuantity = (int) ($part['quantity'] ?? 0);

                if ($partQuantity > 0) {
                    $quantities[] = $partQuantity;
                }
            }

            return $quantities;
        }

        return $quantity > 0 ? [$quantity] : [];
    }

    private function catalogUnitPrice(Order $order, OrderLine $line, int $quantity): ?int
    {
        $key = $order->id.'|'.$line->id.'|'.$quantity;

        if (array_key_exists($key, $this->catalogPrices)) {
            return $this->catalogPrices[$key];
        }

        return $this->catalogPrices[$key] = SplitOrder::make()->catalogUnitPrice($order, $line, $quantity);
    }

    private function moneyLabel(Order $order, int $minor): string
    {
        return trim($this->decimalFromMinor($order, $minor).' '.($order->currency?->code ?? ''));
    }

    /**
     * A blank shipping field, or one the administrator has not edited, follows the price
     * calculated for the products currently assigned to that order.
     */
    private function fillCalculatedShipping(Order $order): void
    {
        $livewire = $this->getLivewire();
        $index = array_key_last($livewire->mountedActions ?? []);

        if ($index === null) {
            return;
        }

        $data = $livewire->mountedActions[$index]['data'] ?? null;

        if (! is_array($data)) {
            return;
        }

        $probe = $data;

        foreach ($probe['orders'] ?? [] as $orderIndex => $orderState) {
            if (! is_array($orderState) || ! $this->followsCalculatedShipping($orderState)) {
                continue;
            }

            $probe['orders'][$orderIndex]['shipping'] = null;
        }

        try {
            $preview = SplitOrder::make()->preview($order, $this->requestFromState($order, $probe));
        } catch (Throwable) {
            return;
        }

        foreach ($preview->targets as $target) {
            foreach ($data['orders'] as $orderIndex => $orderState) {
                if (! is_array($orderState) || (string) ($orderState['key'] ?? '') !== $target->key) {
                    continue;
                }

                if (! $this->followsCalculatedShipping($orderState)) {
                    continue;
                }

                if ($this->minorFromDecimal($order, $orderState['shipping'] ?? null) === $target->shippingMinor) {
                    continue;
                }

                $data['orders'][$orderIndex]['shipping'] = $this->decimalFromMinor($order, $target->shippingMinor);
            }
        }

        $livewire->mountedActions[$index]['data'] = $data;
        self::$previews[$this->previewCacheKey($order, $data)] = $preview;
    }

    /**
     * @param  array<string, mixed>  $orderState
     */
    private function followsCalculatedShipping(array $orderState): bool
    {
        return ! (bool) ($orderState['shipping_custom'] ?? false);
    }

    private function shippingHelper(Order $order, Get $get): ?string
    {
        $preview = $this->rememberPreview($order, $this->mountedData());
        $key = (string) $get('key');
        $target = collect($preview?->targets ?? [])->first(
            fn (SplitOrderPreviewTarget $item): bool => $item->key === $key,
        );

        if ($target === null || ! $target->shippingPricingUnavailable) {
            return null;
        }

        return __('lunarpanel::order.action.split_order.shipping_unavailable');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rememberPreview(Order $order, array $data): ?SplitOrderPreview
    {
        $key = $this->previewCacheKey($order, $data);

        if (array_key_exists($key, self::$previews)) {
            return self::$previews[$key];
        }

        try {
            return self::$previews[$key] = SplitOrder::make()->preview($order, $this->requestFromState($order, $data));
        } catch (Throwable) {
            return self::$previews[$key] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function previewCacheKey(Order $order, array $data): string
    {
        return $order->id.'|'.md5((string) json_encode($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function mountedData(): array
    {
        $livewire = $this->getLivewire();
        $index = array_key_last($livewire->mountedActions ?? []);

        if ($index === null) {
            return [];
        }

        $data = $livewire->mountedActions[$index]['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    private function minorFromDecimal(Order $order, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $factor = (int) ($order->currency?->factor ?: 1);

        return (int) round(((float) $value) * $factor);
    }

    private function decimalFromMinor(Order $order, int $minor): string
    {
        $currency = $order->currency ?? Currency::getDefault();
        $factor = (int) ($currency?->factor ?: 1);
        $places = (int) ($currency?->decimal_places ?? 2);

        return number_format($minor / $factor, $places, '.', '');
    }

    private function paymentType(Order $order, string $paymentOption): ?string
    {
        $original = (string) (data_get($order->meta, 'payment_option') ?? '');

        if ($paymentOption === '' || $paymentOption !== $original) {
            return null;
        }

        $type = data_get($order->meta, 'payment_type');

        return filled($type) ? (string) $type : null;
    }

    /**
     * @return array<string, string>
     */
    private function paymentOptions(): array
    {
        $keys = [
            'offline',
            'cash-in-hand',
            'cash-on-delivery',
            'bank-transfer',
            'stripe-card',
            'stripe-wallet-pay',
            'hosted-payment',
        ];
        $options = [];

        foreach ($keys as $key) {
            $options[$key] = __('lunarpanel::order.infolist.payment_methods.'.$key);
        }

        return $options;
    }

    private function couponCode(Order $order): ?string
    {
        $discountIds = collect($order->discount_breakdown ?? [])
            ->pluck('discount_id')
            ->filter()
            ->unique()
            ->values();

        if ($discountIds->isEmpty()) {
            return null;
        }

        $coupon = Discount::query()
            ->whereIn('id', $discountIds)
            ->whereNotNull('coupon')
            ->value('coupon');

        return filled($coupon) ? (string) $coupon : null;
    }

    private function loyaltySpendExists(Order $order): bool
    {
        return $this->loyaltySpendPoints($order) > 0;
    }

    /**
     * Points already spent on this order. Zero when loyalty is not installed or nothing was spent.
     */
    private function loyaltySpendPoints(Order $order): int
    {
        if (! class_exists(LoyaltyLedger::class) || ! class_exists(LoyaltyEventKey::class) || ! class_exists(LoyaltyTransaction::class)) {
            return 0;
        }

        $table = (new LoyaltyTransaction)->getTable();

        if (! Schema::hasTable($table)) {
            return 0;
        }

        $spend = app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($order->id));

        return (int) ($spend->points ?? 0);
    }

    /**
     * A spent redemption has to move in full. The preview stores how many points this order can take.
     */
    private function loyaltySpendExceedsTarget(Order $order, array $data, string $key): bool
    {
        $spent = $this->loyaltySpendPoints($order);

        if ($spent <= 0) {
            return false;
        }

        $accepted = $this->acceptedLoyaltyPoints($order, $data, $key);

        return $accepted !== null && $accepted < $spent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function acceptedLoyaltyPoints(Order $order, array $data, string $key): ?int
    {
        $preview = $this->rememberPreview($order, $data);

        if (! $preview instanceof SplitOrderPreview) {
            return null;
        }

        $target = collect($preview->targets)->first(
            fn (SplitOrderPreviewTarget $item): bool => $item->key === $key,
        );

        return $target instanceof SplitOrderPreviewTarget ? $target->loyaltyPoints : null;
    }

    private function failureTitle(OrderSplitException $exception): string
    {
        $code = match (true) {
            $exception instanceof UnauthorizedOrderSplitException => 'unauthorized',
            $exception instanceof LockedOrderSplitException => $exception->gates[0] ?? 'closed',
            $exception instanceof InvalidOrderSplitException => $exception->reasons[0] ?? 'invalid',
            default => 'invalid',
        };

        $key = 'lunarpanel::order.action.split_order.gates.'.$code;
        $translated = __($key);

        return $translated === $key
            ? __('lunarpanel::order.action.split_order.invalid')
            : $translated;
    }
}
