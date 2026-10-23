<?php

namespace Lunar\Admin\Support\Infolists\Components;

use Carbon\Carbon;
use Filament\Infolists\Components\Entry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Lunar\DataTypes\Price;
use Lunar\Models\Transaction as TransactionModel;

class Transaction extends Entry
{
    protected string $view = 'lunarpanel::infolists.components.transaction';

    /**
     * Payment drivers whose transaction meta is listed on the transaction card (BT iPay card payments).
     *
     * @var array<int, string>
     */
    protected array $metaDrivers = ['bt-ipay'];

    /**
     * Meta keys left out because the card already shows their value (the reference and the amount).
     *
     * @var array<int, string>
     */
    protected array $hiddenMetaKeys = ['bt_order_id', 'deposited_amount'];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function renderPaymentIcons()
    {
        echo File::get(__DIR__.'/../../../../resources/icons/payment_icons.svg');
    }

    /**
     * The label to show when a transaction was paid via a digital wallet
     * (e.g. Apple Pay, Google Pay), or null when it wasn't.
     */
    public function walletLabel(TransactionModel $transaction): ?string
    {
        return match ($transaction->meta['wallet_type'] ?? null) {
            'apple_pay' => __('Apple Pay'),
            'google_pay' => __('Google Pay'),
            default => null,
        };
    }

    /**
     * The meta of a transaction as label / value pairs, for the drivers whose meta is listed;
     * empty for every other driver.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function metaEntries(TransactionModel $transaction): array
    {
        if (! in_array($transaction->driver, $this->metaDrivers, true)) {
            return [];
        }

        return collect((array) $transaction->meta)
            ->except($this->hiddenMetaKeys)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->map(fn (mixed $value, int|string $key): array => [
                'label' => $this->metaLabel((string) $key),
                'value' => $this->metaValue($transaction, (string) $key, $value),
            ])
            ->values()
            ->all();
    }

    /**
     * The translated label of a meta key, or the key as a headline when it has no translation.
     */
    protected function metaLabel(string $key): string
    {
        $translationKey = "lunarpanel::order.transactions.meta.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : Str::headline($key);
    }

    /**
     * A meta value for display: amounts (`*_amount`) in the transaction currency, times (`*_at`)
     * in the panel timezone and booleans as yes / no.
     */
    protected function metaValue(TransactionModel $transaction, string $key, mixed $value): string
    {
        return match (true) {
            is_bool($value) => __($value ? 'lunarpanel::global.yes' : 'lunarpanel::global.no'),
            is_array($value) => (string) json_encode($value),
            is_numeric($value) && Str::endsWith($key, '_amount') => (new Price((int) $value, $transaction->amount->currency))->formatted(),
            Str::endsWith($key, '_at') => Carbon::parse($value)
                ->setTimezone(config('lunar.panel.timezone') ?? config('app.timezone'))
                ->format('Y-m-d H:i:s'),
            default => (string) $value,
        };
    }
}
