<?php

use Lunar\ERP\Providers\Magister\OrderStatusMapper;

test('status 1 maps to created', function () {
    $map = new OrderStatusMapper;
    expect($map(1, 0))->toBe('created');
});

test('status 2 substatus 21 maps to awaiting-payment', function () {
    $map = new OrderStatusMapper;
    expect($map(2, 21))->toBe('awaiting-payment');
});

test('status 2 substatus 22 maps to payment-received', function () {
    $map = new OrderStatusMapper;
    expect($map(2, 22))->toBe('payment-received');
});

test('status 2 with other substatus maps to created', function () {
    $map = new OrderStatusMapper;
    expect($map(2, 99))->toBe('created');
});

test('status 3 maps to confirmed', function () {
    $map = new OrderStatusMapper;
    expect($map(3, 0))->toBe('confirmed');
});

test('status 4 substatus 41 maps to prepare-shipment', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 41))->toBe('prepare-shipment');
});

test('status 4 substatus 42 maps to prepare-shipment', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 42))->toBe('prepare-shipment');
});

test('status 4 substatus 43 maps to dispatched', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 43))->toBe('dispatched');
});

test('status 4 substatus 44 maps to returned', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 44))->toBe('returned');
});

test('status 4 substatus 45 maps to completed', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 45))->toBe('completed');
});

test('status 4 with other substatus maps to completed', function () {
    $map = new OrderStatusMapper;
    expect($map(4, 0))->toBe('completed');
});

test('status 5 maps to returned', function () {
    $map = new OrderStatusMapper;
    expect($map(5, 0))->toBe('returned');
});

test('status 6 maps to canceled', function () {
    $map = new OrderStatusMapper;
    expect($map(6, 0))->toBe('canceled');
});

test('unknown status maps to created by default', function () {
    $map = new OrderStatusMapper;
    expect($map(99, 0))->toBe('created');
});
