<?php

use Illuminate\Support\Arr;

/**
 * The flattened keys of every language file of a locale.
 *
 * @return list<string>
 */
function translationKeys(string $directory): array
{
    $keys = [];

    foreach (glob($directory.'/*.php') as $file) {
        foreach (array_keys(Arr::dot(require $file)) as $key) {
            $keys[] = basename($file, '.php').'.'.$key;
        }
    }

    sort($keys);

    return $keys;
}

test('the en, hu, and ro language files have the same keys', function (string $pattern) {
    $base = __DIR__.'/../../../packages/product-import/resources/lang/';

    $english = translationKeys($base.sprintf($pattern, 'en'));

    expect($english)->not->toBeEmpty()
        ->and(translationKeys($base.sprintf($pattern, 'hu')))->toBe($english)
        ->and(translationKeys($base.sprintf($pattern, 'ro')))->toBe($english);
})->with([
    'package strings' => ['%s'],
    'panel permission labels' => ['vendor/lunarpanel/%s'],
]);
