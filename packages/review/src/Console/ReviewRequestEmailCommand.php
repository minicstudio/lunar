<?php

namespace Lunar\Review\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Order;

class ReviewRequestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'review:request-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send review reminder emails for orders that have reached the configured status and delay.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $mailer = config('lunar.review.review_reminder_mailer');

        if (! $mailer) {
            $this->info('Review request mailer is not configured. Command skipped.');

            return;
        }

        $targetStatus = config('lunar.review.order_status_for_review_reminder');
        $firstDelay = config('lunar.review.first_reminder_delay_minutes');
        $secondDelay = config('lunar.review.second_reminder_delay_minutes');

        $firstReminderFrom = Carbon::now()->subMinutes($firstDelay);
        $firstReminderTo = Carbon::now()->subMinutes($firstDelay - 1);

        $secondReminderFrom = Carbon::now()->subMinutes($secondDelay);
        $secondReminderTo = Carbon::now()->subMinutes($secondDelay - 1);

        $orders = Order::with('user')
            ->where('status', $targetStatus)
            ->where(function ($query) use ($firstReminderFrom, $firstReminderTo, $secondReminderFrom, $secondReminderTo) {
                $query->where(function ($subQuery) use ($firstReminderFrom, $firstReminderTo) {
                    $subQuery->whereBetween('updated_at', [$firstReminderFrom, $firstReminderTo]);
                })
                    ->orWhere(function ($subQuery) use ($secondReminderFrom, $secondReminderTo) {
                        $subQuery->whereBetween('updated_at', [$secondReminderFrom, $secondReminderTo])
                            ->whereDoesntHave('reviews');
                    });
            })
            ->get();

        foreach ($orders as $index => $order) {
            Mail::to($order->user?->email ?? $order->billingAddress->contact_email)
                ->later(now()->addSeconds($index * 3), new ($mailer)($order));
        }

        $this->info('Review request emails sent successfully!');
    }
}
