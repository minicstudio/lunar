<?php

namespace Lunar\Admin\Support\Actions\Orders;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Lunar\Actions\Orders\CompletePreorderConsultation;
use Lunar\Actions\Orders\DTOs\DuplicateOrderPreview;
use Lunar\Actions\Orders\DuplicateOrder;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Exceptions\Orders\DuplicateOrderException;
use Lunar\Models\Currency;
use Lunar\Models\Order;

class DuplicateOrderAction extends Action
{
    private ?DuplicateOrderPreview $preview = null;

    private int|string|null $previewOrderId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('lunarpanel::order.action.duplicate_order.label'));
        $this->modalHeading(__('lunarpanel::order.action.duplicate_order.label'));
        $this->modalSubmitActionLabel(__('lunarpanel::order.action.duplicate_order.label'));

        $this->visible(fn (?Order $record): bool => $record instanceof Order
            && UpdateOrderProductLines::userCanManageOrders());

        $this->fillForm(fn (Order $record): array => $this->initialState($record));

        $this->schema(fn (Order $record): array => $this->fields($record));

        $this->action(function (Order $record, array $data, Action $action): void {
            $preview = $this->previewFor($record);
            $ships = $preview->shippingMethods !== [];

            try {
                $created = DuplicateOrder::make()->execute(
                    $record,
                    $data['payment_method'] ?? null,
                    $ships ? ($data['shipping_method'] ?? null) : null,
                    $ships ? $this->minorFromDecimal($record, $data['shipping_price'] ?? null) : null,
                );
            } catch (DuplicateOrderException $exception) {
                Notification::make()
                    ->title(__('lunarpanel::order.action.duplicate_order.reasons.'.$exception->reason))
                    ->danger()
                    ->send();

                $action->halt();
            }

            Notification::make()
                ->title(__('lunarpanel::order.action.duplicate_order.notification'))
                ->success()
                ->send();

            $this->getLivewire()->redirect(ManageOrder::getUrl(['record' => $created]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function initialState(Order $order): array
    {
        $preview = $this->previewFor($order);
        $amount = $this->amountFor($order, (string) $preview->shippingMethod);

        return [
            'payment_method' => $preview->paymentMethod,
            'shipping_method' => $preview->shippingMethod,
            'shipping_price' => $amount === null ? null : $this->decimalFromMinor($order, $amount),
            'shipping_custom' => false,
        ];
    }

    /**
     * @return list<Select|TextInput|Hidden>
     */
    private function fields(Order $order): array
    {
        $preview = $this->previewFor($order);

        $fields = [
            Select::make('payment_method')
                ->label(__('lunarpanel::order.action.duplicate_order.payment_method'))
                ->options($this->paymentOptions())
                ->required(),
        ];

        if ($preview->shippingMethods === []) {
            return $fields;
        }

        $fields[] = Select::make('shipping_method')
            ->label(__('lunarpanel::order.action.duplicate_order.shipping_method'))
            ->options($this->shippingLabels($preview))
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set, mixed $state) use ($order): void {
                $amount = $this->amountFor($order, (string) $state);
                $set('shipping_price', $amount === null ? null : $this->decimalFromMinor($order, $amount));
                $set('shipping_custom', false);
            });

        $fields[] = Hidden::make('shipping_custom')->default(false);

        $fields[] = TextInput::make('shipping_price')
            ->label(__('lunarpanel::order.action.duplicate_order.shipping_price'))
            ->numeric()
            ->minValue(0)
            ->suffix(fn (): string => $order->currency?->code ?? '')
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($order): void {
                $amount = $this->amountFor($order, (string) $get('shipping_method'));
                $typed = $this->minorFromDecimal($order, $state);
                $set('shipping_custom', $amount === null || $typed !== $amount);
            });

        return $fields;
    }

    private function previewFor(Order $order): DuplicateOrderPreview
    {
        if ($this->preview instanceof DuplicateOrderPreview && $this->previewOrderId === $order->getKey()) {
            return $this->preview;
        }

        try {
            $this->preview = DuplicateOrder::make()->preview($order);
        } catch (DuplicateOrderException) {
            $this->preview = new DuplicateOrderPreview(
                CompletePreorderConsultation::paymentOptions(),
                null,
                [],
                null,
            );
        }

        $this->previewOrderId = $order->getKey();

        return $this->preview;
    }

    /**
     * @return array<string, string>
     */
    private function paymentOptions(): array
    {
        $options = [];

        foreach (CompletePreorderConsultation::paymentOptions() as $key) {
            $options[$key] = __('lunarpanel::order.infolist.payment_methods.'.$key);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function shippingLabels(DuplicateOrderPreview $preview): array
    {
        $names = [];

        foreach ($preview->shippingMethods as $method) {
            $names[$method->name] = ($names[$method->name] ?? 0) + 1;
        }

        $labels = [];

        foreach ($preview->shippingMethods as $method) {
            $label = $method->name;

            if (($names[$label] ?? 0) > 1 && filled($method->option)) {
                $label .= ' ('.$method->option.')';
            }

            $labels[$method->identifier] = $label;
        }

        return $labels;
    }

    private function amountFor(Order $order, string $identifier): ?int
    {
        if ($identifier === '') {
            return null;
        }

        foreach ($this->previewFor($order)->shippingMethods as $method) {
            if ($method->identifier === $identifier) {
                return $method->amount;
            }
        }

        return null;
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
}
