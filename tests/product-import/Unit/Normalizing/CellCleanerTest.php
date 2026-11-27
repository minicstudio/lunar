<?php

use Lunar\ProductImport\Normalizing\CellCleaner;

test('the escape apostrophe before a formula character is removed and any other apostrophe stays', function (string $cell, string $cleaned) {
    expect((new CellCleaner)->clean('variant.option_value', $cell))->toBe($cleaned);
})->with([
    'minus' => ["'-20%", '-20%'],
    'equals' => ["'=SUM(A1)", '=SUM(A1)'],
    'plus' => ["'+40 723 000 000", '+40 723 000 000'],
    'at' => ["'@home", '@home'],
    'tab' => ["'\tTricou", "\tTricou"],
    'carriage return' => ["'\rTricou", "\rTricou"],
    'letters' => ["'abc", "'abc"],
    'quoted name' => ["'Rochie' de vară", "'Rochie' de vară"],
    'apostrophe alone' => ["'", "'"],
]);

test('the escape apostrophe is removed on every target', function (string $target) {
    expect((new CellCleaner)->clean($target, "'-3"))->toBe('-3');
})->with(['variant.stock', 'variant.sku', 'product.name', 'attribute.material', 'ignore']);

test('a literal line break becomes a line break in descriptions and attributes only', function () {
    $cleaner = new CellCleaner;

    expect($cleaner->clean('product.description', 'Rochie lejeră.\nBumbac 100%.'))->toBe("Rochie lejeră.\nBumbac 100%.")
        ->and($cleaner->clean('product.description', 'Linia 1\r\nLinia 2'))->toBe("Linia 1\nLinia 2")
        ->and($cleaner->clean('attribute.care', 'Spălare la 30°.\nNu se usucă în uscător.'))->toBe("Spălare la 30°.\nNu se usucă în uscător.")
        ->and($cleaner->clean('product.name', 'Rochie\nvară'))->toBe('Rochie\nvară');
});

test('entities in names are decoded until nothing changes', function (string $target) {
    expect((new CellCleaner)->clean($target, 'Dolce &amp;amp; Gabbana'))->toBe('Dolce & Gabbana');
})->with([
    'product.brand',
    'product.category',
    'product.tags',
    'product.properties',
    'variant.option',
    'variant.option_name',
    'variant.option_value',
    'variant.options',
]);

test('entities stay in fields that are not names', function () {
    $cleaner = new CellCleaner;

    expect($cleaner->clean('product.description', '<p>Tom &amp; Jerry</p>'))->toBe('<p>Tom &amp; Jerry</p>')
        ->and($cleaner->clean('variant.sku', 'A&amp;B'))->toBe('A&amp;B')
        ->and($cleaner->clean('product.brand', 'H&#38;M &quot;Studio&quot;'))->toBe('H&M "Studio"');
});
