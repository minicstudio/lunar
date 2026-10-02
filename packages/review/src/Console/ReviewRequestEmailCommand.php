<?php

namespace Lunar\Review\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Lunar\Models\Order;

class ReviewRequestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'review:request-email
        {--window-minutes=60 : Size of the matched updated_at window; must equal the schedule interval (60 for hourly, 1440 for daily)}';

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
        $validator = Validator::make(
            ['window-minutes' => $this->option('window-minutes')],
            ['window-minutes' => ['integer', 'min:1']],
        );

        if ($validator->fails()) {
            $this->fail($validator->errors()->first('window-minutes'));
        }

        $windowMinutes = (int) $this->option('window-minutes');

        $mailer = config('lunar.review.review_reminder_mailer');

        if (! $mailer) {
            $this->info('Review request mailer is not configured. Command skipped.');

            return;
        }

        $targetStatus = config('lunar.review.order_status_for_review_reminder');
        $firstDelay = config('lunar.review.first_reminder_delay_minutes');
        $secondDelay = config('lunar.review.second_reminder_delay_minutes');

        $firstReminderFrom = Carbon::now()->subMinutes($firstDelay);
        $firstReminderTo = Carbon::now()->subMinutes($firstDelay - $windowMinutes);

        $secondReminderFrom = Carbon::now()->subMinutes($secondDelay);
        $secondReminderTo = Carbon::now()->subMinutes($secondDelay - $windowMinutes);

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
