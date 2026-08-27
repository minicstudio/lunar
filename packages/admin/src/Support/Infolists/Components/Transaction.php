<?php

namespace Lunar\Admin\Support\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Support\Facades\File;
use Lunar\Models\Transaction as TransactionModel;

class Transaction extends Entry
{
    protected string $view = 'lunarpanel::infolists.components.transaction';

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath(null);
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
}
