<?php

use Lunar\ProductImport\Normalizing\DateParser;

test('dates are read strictly in their column format', function (string $value, string $format, ?string $expected) {
    expect((new DateParser)->parse($value, $format))->toBe($expected);
})->with([
    'sale start with an hour without a leading zero' => ['2026-12-01 0:00:00', 'Y-m-d G:i:s', '2026-12-01 00:00:00'],
    'sale end' => ['2026-12-24 23:59:59', 'Y-m-d G:i:s', '2026-12-24 23:59:59'],
    'another format' => ['24.12.2026', 'Y-m-d G:i:s', null],
    'dotted date' => ['24.12.2026', 'd.m.Y', '2026-12-24 00:00:00'],
    'hungarian date' => ['2026. 12. 24.', 'Y. m. d.', '2026-12-24 00:00:00'],
    'a thirteenth month' => ['2026-13-01', 'Y-m-d', null],
    'the 30th of february' => ['2026-02-30', 'Y-m-d', null],
    'trailing text' => ['2026-12-24 extra', 'Y-m-d', null],
]);

test('a format with anything but date characters and separators reads nothing', function () {
    expect((new DateParser)->parse('2026-12-24', 'Y-m-d; DROP'))->toBeNull()
        ->and((new DateParser)->parse('2026-12-24', null))->toBeNull();
});

test('cells stored as dates are read in their fixed form', function () {
    expect((new DateParser)->parseTyped('2026-11-18'))->toBe('2026-11-18 00:00:00')
        ->and((new DateParser)->parseTyped('2026-11-18T10:30:00'))->toBe('2026-11-18 10:30:00')
        ->and((new DateParser)->parseTyped('18.11.2026'))->toBeNull();
});
