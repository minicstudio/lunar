<?php

namespace Lunar\Loyalty\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Support\Facades\Mail;
use Lunar\Loyalty\Services\LoyaltyExpirationService;

class NotifyExpiringLoyaltyPointsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'loyalty:notify-expiring-points';

    /**
     * @var string
     */
    protected $description = 'Notify customers about loyalty points expiring soon';

    /**
     * Execute the console command.
     */
    public function handle(LoyaltyExpirationService $expirationService): int
    {
        $windows = config('lunar.loyalty.expiration.notify_windows', []);
        $mailer = config('lunar.loyalty.expiration.notification_mailer');
        $mailableClass = config('lunar.loyalty.expiration.notification_mailable');
        $notified = 0;

        foreach ($windows as $days => $token) {
            $lots = $expirationService->findLotsExpiringWithinDays((int) $days);

            foreach ($lots as $lot) {
                $notifications = $lot->meta['notifications'] ?? [];

                if (in_array($token, $notifications, true)) {
                    continue;
                }

                if (! $mailer || ! is_string($mailableClass) || ! class_exists($mailableClass)) {
                    continue;
                }

                $customer = $lot->loyaltyAccount->customer;
                $user = $customer?->users()->first();

                if (! $user?->email) {
                    continue;
                }

                $mailable = new $mailableClass($lot, $user, (int) $days);

                if (! $mailable instanceof MailableContract) {
                    continue;
                }

                Mail::mailer($mailer)->to($user->email)->send($mailable);

                $notifications[] = $token;
                $lot->update(['meta' => array_merge($lot->meta?->toArray() ?? [], ['notifications' => $notifications])]);
                $notified++;
            }
        }

        $this->info("Sent {$notified} expiration notification(s).");

        return self::SUCCESS;
    }
}
