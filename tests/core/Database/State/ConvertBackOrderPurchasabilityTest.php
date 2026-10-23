<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Database\State\ConvertBackOrderPurchasability;
use Lunar\Models\ProductVariant;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

test('rewrites legacy backorder purchasability and leaves preorder unchanged', function () {
    $legacy = ProductVariant::factory()->create([
        'purchasable' => 'backorder',
    ]);

    $preorder = ProductVariant::factory()->create([
        'purchasable' => 'preorder',
    ]);

    (new ConvertBackOrderPurchasability)->run();

    expect($legacy->fresh()->purchasable)->toBe('in_stock_or_on_backorder')
        ->and($preorder->fresh()->purchasable)->toBe('preorder');
});
