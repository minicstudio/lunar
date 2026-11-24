<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Prompts\AgentPrompt;
use Lunar\Models\Language;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);

    config([
        'lunar.product-import.ai.provider' => 'openai',
        'lunar.product-import.ai.model' => null,
        'lunar.product-import.ai.key' => null,
        'lunar.product-import.ai.url' => null,
        'ai.providers.openai.key' => 'sk-assistant',
    ]);
});

test('the agent sends one prompt and declares no tools', function () {
    ColumnMappingAgent::fake([['columns' => [], 'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 1]]]);

    $agent = new ColumnMappingAgent(['product.name', 'variant.sku', 'ignore'], ['ro']);
    $response = $agent->prompt('{"sheet":"Produse"}');

    ColumnMappingAgent::assertPromptedTimes(1);
    ColumnMappingAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === '{"sheet":"Produse"}');

    expect($agent)->not->toBeInstanceOf(HasTools::class)
        ->and($response['grouping']['strategy'])->toBe('per_row')
        ->and($agent->provider())->toBe('openai')
        ->and($agent->model())->toBeNull();
});

test('with an own key the agent uses its own provider and the assistant config is unchanged', function () {
    config(['lunar.product-import.ai.key' => 'sk-import', 'lunar.product-import.ai.model' => 'gpt-test']);

    ColumnMappingAgent::fake([['columns' => [], 'grouping' => ['strategy' => 'per_row', 'column' => null, 'confidence' => 1]]]);

    $agent = new ColumnMappingAgent(['product.name', 'ignore'], ['ro']);
    $agent->prompt('{}');

    ColumnMappingAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === 'product-import' && $prompt->model === 'gpt-test');

    expect($agent->provider())->toBe('product-import')
        ->and(config('ai.providers.openai.key'))->toBe('sk-assistant')
        ->and(config('ai.providers.product-import'))->toBeNull()
        ->and(config('ai.default'))->toBe('openai');
});

test('the schema limits targets and languages to the allowed lists', function () {
    $agent = new ColumnMappingAgent(['product.name', 'variant.sku', 'ignore'], ['ro', 'hu']);

    $schema = json_decode((string) (new ObjectType($agent->schema(new JsonSchemaTypeFactory))), true);

    expect($schema['properties']['columns']['items']['properties']['target']['enum'])->toBe(['product.name', 'variant.sku', 'ignore'])
        ->and($schema['properties']['columns']['items']['properties']['locale']['enum'])->toBe(['ro', 'hu'])
        ->and($schema['properties']['grouping']['properties']['strategy']['enum'])->toBe(['by_column', 'by_parent', 'by_name', 'per_row'])
        ->and($schema['properties']['columns']['items']['properties'])->toHaveKey('pair');
});

test('the schema has a number and a date format per column and no sheet-level decimal comma', function () {
    $agent = new ColumnMappingAgent(['product.name', 'variant.price', 'ignore'], ['ro']);

    $schema = json_decode((string) (new ObjectType($agent->schema(new JsonSchemaTypeFactory))), true);
    $column = $schema['properties']['columns']['items']['properties'];

    expect($column['number_format']['properties']['decimal_separator']['enum'])->toBe(['.', ','])
        ->and($column['number_format']['properties']['thousands_separator']['enum'])->toBe(['none', '.', ',', 'space', "'"])
        ->and($column['number_format']['type'])->toContain('null')
        ->and($column['date_format']['type'])->toContain('null')
        ->and((string) $agent->instructions())->toContain('A format belongs to one column')
        ->and((string) $agent->instructions())->not->toContain('decimal comma hint')
        ->and((string) $agent->instructions())->not->toContain('inferred type')
        ->and((string) $agent->instructions())->not->toContain('column type');
});

test('the schema has the conventions per column and only the grouping and units per sheet', function () {
    $agent = new ColumnMappingAgent(['product.status', 'source.type', 'product.images', 'ignore'], ['ro']);

    $schema = json_decode((string) (new ObjectType($agent->schema(new JsonSchemaTypeFactory))), true);
    $column = $schema['properties']['columns']['items']['properties'];

    expect($schema['properties'])->not->toHaveKey('source')
        ->and(array_keys($schema['properties']))->toBe(['columns', 'grouping', 'units'])
        ->and($column['values']['type'])->toContain('array')
        ->and($column['values']['type'])->toContain('null')
        ->and($column['values']['items']['properties']['meaning']['enum'])->toBe([
            'published', 'draft', 'in_stock', 'out_of_stock', 'on_backorder', 'yes', 'no', 'empty',
            'single', 'parent', 'variant', 'skip', 'not_shippable',
        ])
        ->and($column['values']['items']['required'])->toBe(['value', 'meaning'])
        ->and($column['item_parts']['items']['enum'])->toBe(['name', 'value', 'skip'])
        ->and($column['image_suffix']['type'])->toContain('null')
        ->and($schema['properties']['columns']['items']['required'])->toContain('values', 'item_parts', 'image_suffix');
});

test('the instructions describe each export convention and name no platform', function () {
    $instructions = (string) (new ColumnMappingAgent(['product.name', 'ignore'], ['ro']))->instructions();

    expect($instructions)
        ->toContain('Row types.', '"single"', '"parent"', '"variant"', '"skip"', '"not_shippable"')
        ->toContain('Parent references.', '"id:57"', 'a separate variants file')
        ->toContain('Renumbered pairs.', 'differently from its product row', 'how an attribute is displayed')
        ->toContain('Packed items.', 'item_parts', '["name", "skip", "skip"]')
        ->toContain('Code meanings.', '"empty"', 'known to hold even when the samples do not show')
        ->toContain('Image size suffixes.', 'image_suffix')
        ->toContain('Prices with and without tax.', 'matches the price target')
        ->toContain('Columns without a catalog counterpart are ignored', 'tax status and tax classes')
        ->toContain('"1256,59"', '"1.250" is 1.25')
        ->not->toContain('platform');
});

test('the schema has the list, path, and item separators per column and no sheet hints', function () {
    $agent = new ColumnMappingAgent(['product.category', 'variant.options', 'ignore'], ['ro']);

    $schema = json_decode((string) (new ObjectType($agent->schema(new JsonSchemaTypeFactory))), true);
    $column = $schema['properties']['columns']['items'];

    expect($schema['properties'])->not->toHaveKey('hints')
        ->and($column['required'])->toContain('list_separator', 'path_separator', 'item_separator')
        ->and($column['properties']['list_separator']['type'])->toBe(['string', 'null'])
        ->and($column['properties']['path_separator']['type'])->toBe(['string', 'null'])
        ->and($column['properties']['item_separator']['type'])->toBe(['string', 'null'])
        ->and((string) $agent->instructions())
        ->toContain('Separators.', 'must have its list_separator', 'Give "none" when each cell holds', 'path_separator', 'item_separator')
        ->not->toContain('Optionally give hints');
});

test('the unit enums are the store units', function () {
    $weightUnits = array_keys(config('lunar.shipping.measurements.weight'));
    $lengthUnits = array_keys(config('lunar.shipping.measurements.length'));
    $agent = new ColumnMappingAgent(['variant.weight', 'ignore'], ['ro'], $weightUnits, $lengthUnits);

    $schema = json_decode((string) (new ObjectType($agent->schema(new JsonSchemaTypeFactory))), true);
    $units = $schema['properties']['units'];

    expect($units['required'])->toBe(['weight_unit', 'length_unit'])
        ->and($units['properties']['weight_unit']['enum'])->toBe($weightUnits)
        ->and($units['properties']['weight_unit']['type'])->toBe(['string', 'null'])
        ->and($units['properties']['length_unit']['enum'])->toBe($lengthUnits)
        ->and($units['properties']['length_unit']['type'])->toBe(['string', 'null'])
        ->and((string) $agent->instructions())->toContain('Units.', 'Give null only for a unit whose columns the sheet does not');
});

test('the instructions map seo columns to the attributes that hold them and slugs to the url slug, and no longer ignore them', function () {
    $instructions = (string) (new ColumnMappingAgent(['product.name', 'product.slug', 'ignore'], ['ro']))->instructions();
    $ignored = Str::after($instructions, 'Columns without a catalog counterpart are ignored');

    expect($instructions)
        ->toContain('SEO fields and URL slugs.', 'meta title, meta description, or meta keywords column maps to the', 'attribute of the product type that holds it')
        ->toContain('Search Engine Optimization › Title', 'ignore the column')
        ->toContain('"product.slug"', 'A column of')
        ->toContain('full product URLs is ignored')
        ->and($ignored)->not->toContain('SEO')
        ->not->toContain('slug')
        ->not->toContain('meta');
});
