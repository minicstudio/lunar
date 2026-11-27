<?php

use Lunar\ProductImport\Analysis\ColumnProfiler;
use Lunar\ProductImport\Reading\ReadRow;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class);

test('a column of decimal commas is profiled at full fill, without a type or a decimal separator', function () {
    $profiles = app(ColumnProfiler::class)->profile(
        [0 => 'Pret'],
        [[0 => '49,90'], [0 => '129,00'], [0 => '15,5']],
    );

    expect($profiles[0])->toBe([
        'index' => 0,
        'header' => 'Pret',
        'fill_rate' => 1.0,
        'distinct_share' => 1.0,
        'samples' => ['49,90', '129,00', '15,5'],
        'samples_typed' => false,
    ]);
});

test('profiles have no type', function () {
    $profiles = app(ColumnProfiler::class)->profile(
        [0 => 'SKU', 1 => 'Data', 2 => 'Activ', 3 => 'Nota'],
        [[0 => 'A-1', 1 => '2026-11-18', 2 => 'da'], [0 => 'A-2', 1 => '2026-11-19', 2 => 'nu']],
    );

    foreach ($profiles as $profile) {
        expect($profile)->not->toHaveKey('type');
    }
});

test('128 rows with 34 parent codes give a distinct share of about 27%', function () {
    $rows = array_map(fn (int $i) => [0 => 'TB-'.str_pad((string) ($i % 34), 3, '0', STR_PAD_LEFT)], range(1, 128));

    $profile = app(ColumnProfiler::class)->profile([0 => 'Cod parinte'], $rows)[0];

    expect($profile['fill_rate'])->toBe(1.0)
        ->and(round($profile['distinct_share'] * 100))->toBe(27.0);
});

test('samples are capped at 30 values of 100 characters', function () {
    $rows = array_map(fn (int $i) => [0 => str_repeat(chr(97 + $i % 10), 150).$i], range(1, 50));

    $samples = app(ColumnProfiler::class)->profile([0 => 'Descriere'], $rows)[0]['samples'];

    expect($samples)->toHaveCount(30)
        ->and(max(array_map('mb_strlen', $samples)))->toBe(100);
});

test('a text column with 50 distinct values keeps 30 samples, like a number column', function () {
    $rows = array_map(fn (int $i) => [0 => 'Produs '.$i, 1 => $i.',90'], range(1, 50));

    $profiles = app(ColumnProfiler::class)->profile([0 => 'Nume', 1 => 'Pret'], $rows);

    expect($profiles[0]['samples'])->toHaveCount(30)
        ->and($profiles[1]['samples'])->toHaveCount(30);
});

test('an empty fifth header becomes column 5', function () {
    $profiles = app(ColumnProfiler::class)->profile(
        [0 => 'SKU', 1 => 'Denumire', 2 => 'Pret', 3 => 'Stoc'],
        [[0 => 'A-1', 1 => 'Tricou', 2 => '10', 3 => '1', 4 => 'nota']],
    );

    expect($profiles)->toHaveCount(5)
        ->and($profiles[4]['header'])->toBe('Column 5')
        ->and($profiles[4]['samples'])->toBe(['nota']);
});

test('a column without values has no fill and no samples', function () {
    $profile = app(ColumnProfiler::class)->profile([0 => 'SKU', 1 => 'Nota'], [[0 => 'A-1'], [0 => 'A-2']])[1];

    expect($profile)->toMatchArray(['fill_rate' => 0.0, 'distinct_share' => 0.0, 'samples' => []]);
});

test('the fill rate counts the empty cells of the column', function () {
    $profile = app(ColumnProfiler::class)->profile([0 => 'SKU', 1 => 'EAN'], [[0 => 'A', 1 => '5941234567890'], [0 => 'B'], [0 => 'C'], [0 => 'D']])[1];

    expect($profile['fill_rate'])->toBe(0.25);
});

test('weights with a unit and N/A cells stay samples as they are', function () {
    $values = ['1,25 kg', 'N/A', '0,5 kg', 'N/A', '2 kg', 'N/A'];
    $rows = array_map(fn (string $value) => [0 => $value], $values);

    $profile = app(ColumnProfiler::class)->profile([0 => 'Greutate'], $rows)[0];

    expect($profile['samples'])->toBe(['1,25 kg', 'N/A', '0,5 kg', '2 kg']);
});

test('a column with number cells and text cells samples the text cells', function () {
    $rows = [
        new ReadRow(2, [0 => '1256.59'], [0]),
        new ReadRow(3, [0 => '49,90'], []),
        new ReadRow(4, [0 => '310'], [0]),
        new ReadRow(5, [0 => '15,5'], []),
    ];

    $profile = app(ColumnProfiler::class)->profile([0 => 'Pret'], $rows)[0];

    expect($profile['samples'])->toBe(['49,90', '15,5'])
        ->and($profile['samples_typed'])->toBeFalse()
        ->and($profile['distinct_share'])->toBe(1.0);
});

test('a column of typed cells samples them and says so', function () {
    $rows = [
        new ReadRow(2, [0 => '2026-11-18', 1 => '5941234567890'], [0, 1]),
        new ReadRow(3, [0 => '2026-11-19', 1 => '5941234567891'], [0, 1]),
    ];

    $profiles = app(ColumnProfiler::class)->profile([0 => 'Data', 1 => 'EAN'], $rows);

    expect($profiles[0]['samples'])->toBe(['2026-11-18', '2026-11-19'])
        ->and($profiles[0]['samples_typed'])->toBeTrue()
        ->and($profiles[1]['samples_typed'])->toBeTrue();
});
