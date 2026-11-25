<?php

use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'lunar.product-import.ai.provider' => null,
        'lunar.product-import.ai.model' => null,
        'lunar.product-import.ai.fallback_models' => null,
        'lunar.product-import.ai.key' => null,
        'lunar.product-import.ai.url' => null,
        'ai.providers.openai.key' => null,
        'ai.providers.gemini.key' => null,
    ]);
});

test('without a provider the analysis is not configured', function () {
    expect(app(AiAvailability::class)->isConfigured())->toBeFalse()
        ->and(app(AiAvailability::class)->label())->toBe('AI analysis is not configured.');
});

test('openai without any key is not configured', function () {
    config(['lunar.product-import.ai.provider' => 'openai']);

    expect(app(AiAvailability::class)->isConfigured())->toBeFalse();
});

test('the provider key from the ai config is enough', function () {
    config(['lunar.product-import.ai.provider' => 'openai', 'ai.providers.openai.key' => 'sk-shared']);

    expect(app(AiAvailability::class)->isConfigured())->toBeTrue()
        ->and(app(AiAvailability::class)->usesOwnConfiguration())->toBeFalse();
});

test('an own key is enough', function () {
    config(['lunar.product-import.ai.provider' => 'openai', 'lunar.product-import.ai.key' => 'sk-import']);

    expect(app(AiAvailability::class)->isConfigured())->toBeTrue()
        ->and(app(AiAvailability::class)->usesOwnConfiguration())->toBeTrue();
});

test('the ai line names the provider and the default model', function () {
    config(['lunar.product-import.ai.provider' => 'gemini', 'ai.providers.gemini.key' => 'g-key']);

    expect(app(AiAvailability::class)->label())->toBe('AI analysis: gemini · default model');

    config(['lunar.product-import.ai.model' => 'gemini-2.5-flash']);

    expect(app(AiAvailability::class)->label())->toBe('AI analysis: gemini · gemini-2.5-flash');
});

test('without fallback models the list is empty', function () {
    expect(app(AiAvailability::class)->fallbackModels())->toBe([]);
});

test('the fallback models are read from a comma-separated list without blanks, repeats, and the configured model', function () {
    config([
        'lunar.product-import.ai.model' => 'gemini-3.5-flash-lite',
        'lunar.product-import.ai.fallback_models' => ' gemini-3.5-flash, ,gemini-3.5-flash-lite,gemini-3.6-flash,gemini-3.5-flash ',
    ]);

    expect(app(AiAvailability::class)->fallbackModels())->toBe(['gemini-3.5-flash', 'gemini-3.6-flash']);
});

test('the fallback models may be an array', function () {
    config(['lunar.product-import.ai.fallback_models' => ['gemini-3.5-flash', 'gemini-3.6-flash']]);

    expect(app(AiAvailability::class)->fallbackModels())->toBe(['gemini-3.5-flash', 'gemini-3.6-flash']);
});
