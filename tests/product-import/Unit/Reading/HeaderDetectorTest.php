<?php

use Lunar\ProductImport\Reading\HeaderDetector;

test('a title row and an empty row above the header give header row 3', function () {
    $rows = [
        1 => ['Lista produse noiembrie 2026'],
        // Row 2 is empty and never reaches the detector.
        3 => ['Cod', 'Denumire', 'Pret', 'Stoc', 'Marime', 'Culoare'],
        4 => ['TB-001-S', 'Tricou basic', '49,90', '12', 'S', 'Rosu'],
        5 => ['TB-001-M', 'Tricou basic', '49,90', '8', 'M', 'Rosu'],
        6 => ['TB-001-L', 'Tricou basic', '49,90', '0', 'L', 'Rosu'],
    ];

    expect((new HeaderDetector)->detect($rows))->toBe(3);
});

test('the first row is the header of a plain sheet', function () {
    $rows = [
        1 => ['SKU', 'Name', 'Price'],
        2 => ['A-1', 'Shirt', '10'],
        3 => ['A-2', 'Pants', '20'],
    ];

    expect((new HeaderDetector)->detect($rows))->toBe(1);
});

test('a header with one empty cell above a filled column is still the header', function () {
    $rows = [
        1 => ['SKU', 'Name', 'Price', 'Stock', null, 'Color'],
        2 => ['A-1', 'Shirt', '10', '3', 'note', 'Red'],
        3 => ['A-2', 'Pants', '20', '4', 'note', 'Blue'],
    ];

    expect((new HeaderDetector)->detect($rows))->toBe(1);
});

test('rows of numbers are not taken as the header', function () {
    $rows = [
        2 => ['1', '2', '3'],
        3 => ['SKU', 'Name', 'Price'],
        4 => ['A-1', 'Shirt', '10'],
    ];

    expect((new HeaderDetector)->detect($rows))->toBe(3);
});

test('an empty sheet has header row 1', function () {
    expect((new HeaderDetector)->detect([]))->toBe(1);
});
