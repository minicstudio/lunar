<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lunar\Feedback\Models\Feedback;
use Lunar\Models\Order;
use Lunar\Tests\Feedback\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
});

/**
 * Insert a feedback row with sensible defaults.
 */
function createFeedbackRow(array $attributes = []): Feedback
{
    return Feedback::query()->create(array_merge([
        'order_id' => null,
        'user_id' => null,
        'type' => 'checkout.shopping_experience',
        'score' => 5,
        'comment' => null,
        'locale' => 'en',
    ], $attributes));
}

test('migration creates the prefixed feedback table with nullable order_id', function () {
    $table = config('lunar.database.table_prefix').'feedback';

    expect((new Feedback)->getTable())->toBe($table)
        ->and(Schema::hasColumns($table, ['id', 'order_id', 'user_id', 'type', 'score', 'comment', 'locale', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumn($table, 'context'))->toBeFalse();

    $orderId = collect(Schema::getColumns($table))->firstWhere('name', 'order_id');

    expect($orderId['nullable'])->toBeTrue();
});

test('migration adds a unique index on order_id and type', function () {
    $table = config('lunar.database.table_prefix').'feedback';

    $unique = collect(Schema::getIndexes($table))
        ->first(fn (array $index): bool => $index['unique'] && ! $index['primary']);

    expect($unique)->not->toBeNull()
        ->and($unique['columns'])->toBe(['order_id', 'type']);
});

test('the same order and type cannot be stored twice', function () {
    $order = Order::factory()->create();

    createFeedbackRow(['order_id' => $order->id]);

    expect(fn () => createFeedbackRow(['order_id' => $order->id, 'score' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the same order can store another type', function () {
    $order = Order::factory()->create();

    createFeedbackRow(['order_id' => $order->id]);
    createFeedbackRow(['order_id' => $order->id, 'type' => 'order.delivery_experience']);

    expect(Feedback::query()->where('order_id', $order->id)->count())->toBe(2);
});

test('rows without an order are allowed more than once for the same type', function () {
    createFeedbackRow(['type' => 'search.results']);
    createFeedbackRow(['type' => 'search.results']);

    expect(Feedback::query()->whereNull('order_id')->count())->toBe(2);
});

test('existsFor matches the order and type only', function () {
    $order = Order::factory()->create();

    createFeedbackRow(['order_id' => $order->id]);

    expect(Feedback::existsFor($order->id, 'checkout.shopping_experience'))->toBeTrue()
        ->and(Feedback::existsFor($order->id, 'order.delivery_experience'))->toBeFalse()
        ->and(Feedback::existsFor($order->id + 1, 'checkout.shopping_experience'))->toBeFalse()
        ->and(Feedback::existsFor(0, 'checkout.shopping_experience'))->toBeFalse()
        ->and(Feedback::existsFor($order->id, ''))->toBeFalse();
});

test('order relations expose all feedback and the checkout shopping experience row', function () {
    $order = Order::factory()->create();

    $checkout = createFeedbackRow(['order_id' => $order->id, 'score' => 2]);
    createFeedbackRow(['order_id' => $order->id, 'type' => 'order.delivery_experience', 'score' => 4]);

    $order->refresh();

    expect($order->feedback)->toHaveCount(2)
        ->and($order->shoppingExperienceFeedback?->id)->toBe($checkout->id)
        ->and($checkout->order->is($order))->toBeTrue();
});

test('deleting the order keeps the feedback row with a null order_id', function () {
    $order = Order::factory()->create();
    $feedback = createFeedbackRow(['order_id' => $order->id]);

    $order->delete();

    expect($feedback->refresh()->order_id)->toBeNull();
});
