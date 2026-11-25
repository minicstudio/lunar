<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A sheet with a price, a discount amount and percent, and a sale start and
 * end, read with the given date format.
 */
function discountMapping(string $dateFormat): SheetMapping
{
    return new SheetMapping(productTypeId: 1, columns: [
        new ColumnMapping(0, 'Pret', 'variant.price', format: ColumnFormat::number(',')),
        new ColumnMapping(1, 'Reducere', 'variant.discount_amount', format: ColumnFormat::number(',')),
        new ColumnMapping(2, 'Reducere %', 'variant.discount_percent', format: ColumnFormat::number(',')),
        new ColumnMapping(3, 'Inceput', 'variant.sale_starts', format: ColumnFormat::date($dateFormat)),
        new ColumnMapping(4, 'Sfarsit', 'variant.sale_ends', format: ColumnFormat::date($dateFormat)),
    ]);
}

/**
 * The discount of a row of cells.
 *
 * @param  list<string>  $cells
 */
function rowDiscount(array $cells, string $dateFormat = 'Y-m-d'): ?RowDiscount
{
    return RowDiscount::for(app(RowNormalizer::class)->normalize($cells, discountMapping($dateFormat), 100));
}

test('an end date without a time lasts until the end of that day, and a start begins with it', function () {
    $discount = rowDiscount(['229,00', '', '30', '2026-12-01', '2026-12-31']);

    expect($discount->kind)->toBe(RowDiscount::PERCENT)
        ->and($discount->value)->toBe(30.0)
        ->and($discount->startsAt)->toBe('2026-12-01 00:00:00')
        ->and($discount->endsAt)->toBe('2026-12-31 23:59:59')
        ->and($discount->key())->toBe('percent:30|2026-12-01 00:00:00|2026-12-31 23:59:59');
});

test('a date format with a time keeps the time of the end', function () {
    $discount = rowDiscount(['229,00', '', '30', '', '2026-12-31 18:00:00'], 'Y-m-d G:i:s');

    expect($discount->startsAt)->toBeNull()
        ->and($discount->endsAt)->toBe('2026-12-31 18:00:00');
});

test('an amount is read in minor units of the default currency', function () {
    $discount = rowDiscount(['199,00', '20,00', '', '', '']);

    expect($discount->kind)->toBe(RowDiscount::AMOUNT)
        ->and($discount->value)->toBe(2000)
        ->and($discount->key())->toBe('amount:2000||')
        ->and($discount->toArray())->toBe(['kind' => 'amount', 'value' => 2000, 'starts_at' => null, 'ends_at' => null]);
});

test('a row without a discount, with both kinds, with a percent above 100, or with a start after its end gives none', function (array $cells) {
    expect(rowDiscount($cells))->toBeNull();
})->with([
    'no discount' => [['199,00', '', '', '', '']],
    'zero percent' => [['199,00', '', '0', '', '']],
    'amount and percent' => [['199,00', '20,00', '10', '', '']],
    'percent above 100' => [['199,00', '', '150', '', '']],
    'start after end' => [['199,00', '', '10', '2026-12-31', '2026-12-01']],
]);

test('a discount knows when it ended and when an amount leaves too little of the price', function () {
    $now = CarbonImmutable::parse('2026-11-24 10:00:00');

    expect((new RowDiscount('percent', 30, null, '2026-11-01 23:59:59'))->hasEnded($now))->toBeTrue()
        ->and((new RowDiscount('percent', 30, null, '2026-11-24 23:59:59'))->hasEnded($now))->toBeFalse()
        ->and((new RowDiscount('percent', 30))->hasEnded($now))->toBeFalse()
        ->and((new RowDiscount('amount', 5500))->leavesTooLittleOf(5900))->toBeTrue()
        ->and((new RowDiscount('amount', 5300))->leavesTooLittleOf(5900))->toBeFalse()
        ->and((new RowDiscount('percent', 95))->leavesTooLittleOf(5900))->toBeFalse();
});

test('a discount is described with its value and dates', function () {
    expect((new RowDiscount('percent', 30, null, '2026-12-31 23:59:59'))->describe())->toBe('30% · until 31 Dec 2026')
        ->and((new RowDiscount('percent', 12.5, '2026-12-01 00:00:00', '2026-12-31 23:59:59'))->describe())->toBe('12.5% · from 1 Dec 2026 until 31 Dec 2026')
        ->and((new RowDiscount('amount', 2000, '2026-12-01 08:00:00'))->describe(100, 'RON'))->toBe('20.00 RON · from 1 Dec 2026 08:00')
        ->and((new RowDiscount('amount', 2000))->describe())->toBe('20.00');
});

test('a discount survives its stored details', function () {
    $discount = new RowDiscount('percent', 30, null, '2026-12-31 23:59:59');

    expect(RowDiscount::fromArray($discount->toArray()))->toEqual($discount)
        ->and(RowDiscount::fromArray(['kind' => 'other', 'value' => 3]))->toBeNull()
        ->and(RowDiscount::fromArray(null))->toBeNull();

    expect(RowDiscount::for(new NormalizedRow(['variant.discount_percent' => 30.0])))->toEqual(new RowDiscount('percent', 30.0));
});
