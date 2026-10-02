<?php

uses(\Lunar\Tests\Review\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Tests\Review\Stubs\TestReviewReminderMail;

beforeEach(function () {
    Mail::fake();

    Config::set('lunar.review.review_reminder_mailer', TestReviewReminderMail::class);
    Config::set('lunar.review.order_status_for_review_reminder', 'completed');
    Config::set('lunar.review.first_reminder_delay_minutes', 15 * 24 * 60);

    $this->order = Order::factory()->create([
        'status' => 'completed',
        'updated_at' => now()->subMinutes(15 * 24 * 60 - 120),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $this->order->id,
        'type' => 'billing',
        'contact_email' => 'customer@example.com',
    ]);
});

test('skips orders outside the default hourly window', function () {
    $this->artisan('review:request-email')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('sends reminders for orders inside a daily window', function () {
    $this->artisan('review:request-email', ['--window-minutes' => 24 * 60])->assertSuccessful();

    Mail::assertQueued(
        TestReviewReminderMail::class,
        fn (TestReviewReminderMail $mail) => $mail->order->is($this->order) && $mail->hasTo('customer@example.com')
    );
});

test('fails when the window is not a positive integer', function (mixed $windowMinutes) {
    $this->artisan('review:request-email', ['--window-minutes' => $windowMinutes])->assertFailed();

    Mail::assertNothingQueued();
})->with([0, 'abc']);
