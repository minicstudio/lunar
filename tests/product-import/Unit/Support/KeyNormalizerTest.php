<?php

use Lunar\ProductImport\Support\KeyNormalizer;

test('values equal after ignoring case, accents, and spaces have one key', function (string $a, string $b) {
    expect(KeyNormalizer::key($a))->toBe(KeyNormalizer::key($b));
})->with([
    'case and trailing space' => ['Nike', 'NIKE '],
    'accents' => ['Vară', 'vara'],
    'romanian comma below' => ['Roșie', 'rosie'],
    'hungarian' => ['Hosszú ujjú', 'hosszu UJJU'],
    'repeated spaces' => ['Femei  >   Tricouri', 'femei > tricouri'],
]);

test('different values keep different keys', function () {
    expect(KeyNormalizer::key('Hanorac'))->not->toBe(KeyNormalizer::key('Hanorace'));
});

test('a no-break space after a value gives the same key', function () {
    expect(KeyNormalizer::key("Nike\u{00A0}"))->toBe(KeyNormalizer::key('NIKE'))
        ->and(KeyNormalizer::key("Nike\u{00A0}"))->toBe('nike');
});

test('the existing keys are unchanged', function (string $value, string $key) {
    expect(KeyNormalizer::key($value))->toBe($key);
})->with([
    ['Femei  >   Tricouri', 'femei > tricouri'],
    ['Vară', 'vara'],
    ['Roșie', 'rosie'],
    ['  Hosszú ujjú ', 'hosszu ujju'],
]);
