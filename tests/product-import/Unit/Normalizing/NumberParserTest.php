<?php

use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Normalizing\NumberParser;

test('numbers are read strictly in their column format', function (string $value, ColumnFormat $format, ?string $expected) {
    expect((new NumberParser)->parse($value, $format))->toBe($expected);
})->with([
    'export price with a decimal comma' => ['1256,59', ColumnFormat::number(','), '1256.59'],
    'export weight with a dot' => ['1.250', ColumnFormat::number('.'), '1.250'],
    'raw price with six decimals' => ['1256.590000', ColumnFormat::number('.'), '1256.590000'],
    'formatted price with a currency' => ['1.503,85 lei', ColumnFormat::number(',', '.'), '1503.85'],
    'romanian price with currency' => ['1.249,90 lei', ColumnFormat::number(',', '.'), '1249.90'],
    'decimal dot' => ['49.90', ColumnFormat::number('.'), '49.90'],
    'english thousands' => ['1,249.90', ColumnFormat::number('.', ','), '1249.90'],
    'short decimal comma' => ['15,5', ColumnFormat::number(','), '15.5'],
    'integer' => ['129', ColumnFormat::number('.'), '129'],
    'leading zeros' => ['007', ColumnFormat::number('.'), '7'],
    'hungarian price with a no-break space' => ["1\u{00A0}503,85 lei", ColumnFormat::number(',', ' '), '1503.85'],
    'narrow no-break space' => ["1\u{202F}249,90 RON", ColumnFormat::number(',', ' '), '1249.90'],
    'plain space' => ['1 249,90', ColumnFormat::number(',', ' '), '1249.90'],
    'apostrophe thousands' => ["1'250.50", ColumnFormat::number('.', "'"), '1250.50'],
    'euro symbol' => ['€ 19.99', ColumnFormat::number('.'), '19.99'],
    'thousands without a decimal' => ['1.250', ColumnFormat::number(',', '.'), '1250'],
    'repeated thousands' => ['1.249.000', ColumnFormat::number(',', '.'), '1249000'],
    'digits without the thousands separator' => ['1503,85', ColumnFormat::number(',', '.'), '1503.85'],
    'negative' => ['-5', ColumnFormat::number('.'), '-5'],
    'a comma in a dot column is not a number, not 125' => ['1,25', ColumnFormat::number('.'), null],
    'irregular groups' => ['12.34.56', ColumnFormat::number(',', '.'), null],
    'irregular groups with a dot decimal' => ['12.34.56', ColumnFormat::number('.'), null],
    'a group of four digits' => ['1.2345,6', ColumnFormat::number(',', '.'), null],
    'two decimal separators' => ['1,2,3', ColumnFormat::number(','), null],
    'text' => ['la comanda', ColumnFormat::number(','), null],
    'empty' => ['', ColumnFormat::number(','), null],
    'only a separator' => [',', ColumnFormat::number(','), null],
]);

test('a column without a usable number format reads nothing', function (ColumnFormat $format) {
    expect((new NumberParser)->parse('49,90', $format))->toBeNull()
        ->and((new NumberParser)->parse('49,90', null))->toBeNull();
})->with([
    'no format' => [new ColumnFormat],
    'the same separator twice' => [ColumnFormat::number(',', ',')],
    'a date format only' => [ColumnFormat::date('Y-m-d')],
]);
