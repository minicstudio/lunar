<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ExportFixtures;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    ProductType::factory()->create();
    $this->group = CollectionGroup::factory()->create();

    AiFakes::configure();
    ColumnMappingAgent::fake(fn (string $prompt) => ExportFixtures::rowTypeAnswer(str_contains($prompt, '"sheet":"product-export-hu.csv"') ? 'hu' : 'en'));
});

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Upload the row type export in the given language, read and analyze it
 * with the faked answer, and stage it with the status taken from the
 * published column.
 */
function stageRowTypeExport(string $language = 'en'): ProductImport
{
    $import = ImportBuilder::uploaded(["product-export-{$language}.csv" => ExportFixtures::rowTypeExport($language)], [
        'options' => (new ImportOptions(newProductStatus: NewProductStatus::FromColumn, collectionGroupId: test()->group->id))->toArray(),
    ]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    app(ContinueToStaging::class)->handle($import->fresh());

    return $import->fresh();
}

/**
 * The staged row with the export's ID.
 */
function exportRow(ProductImport $import, string $id): ProductImportRow
{
    return ProductImportRow::includedIn($import)->where('source_id', $id)->sole();
}

test('the column conventions come from the answer, and the row type is mapped without any setting', function () {
    $import = stageRowTypeExport();
    $mapping = $import->sheets()->sole()->sheetMapping();

    expect($mapping->column(1)->target)->toBe('source.type')
        ->and($mapping->column(1)->format->values)->toMatchArray(['simple' => 'single', 'variable' => 'parent', 'grouped' => 'skip'])
        ->and($mapping->column(5)->format->values)->toMatchArray(['2' => 'draft'])
        ->and($mapping->column(16)->format->values)->toMatchArray(['notify' => 'yes'])
        ->and($mapping->toArray())->not->toHaveKey('source');
});

test('a parent row has its variant rows as variants and no variant of its own', function () {
    $import = stageRowTypeExport();

    $parent = exportRow($import, '57');
    $group = ProductImportRow::includedIn($import)->where('group_key', $parent->group_key)->orderBy('row_number')->get();

    expect($import->status)->toBe(ImportStatus::ReviewingValues)
        ->and($parent->role)->toBe(RowRole::Product)
        ->and($parent->status)->not->toBe(RowStatus::Error)
        ->and($group->pluck('row_number')->all())->toBe([3, 4, 90])
        ->and($group->where('role', RowRole::Variant)->pluck('source_id')->all())->toBe(['58', '59'])
        ->and(exportRow($import, '60')->role)->toBe(RowRole::ProductAndVariant)
        ->and(exportRow($import, '80')->status)->not->toBe(RowStatus::Error);
});

test('rows of the types that are not imported are skipped with messages naming the type', function () {
    $import = stageRowTypeExport();

    $grouped = exportRow($import, '90');
    $external = exportRow($import, '95');

    expect($grouped->status)->toBe(RowStatus::Skipped)
        ->and($grouped->skip_reason)->toBe(RowSkipReason::RowType)
        ->and(collect($grouped->messages)->pluck('message')->all())->toBe(['Rows of the type “grouped” are not imported.'])
        ->and($external->status)->toBe(RowStatus::Skipped)
        ->and(collect($external->messages)->pluck('message')->all())->toBe(['Rows of the type “external” are not imported.'])
        ->and(exportRow($import, '10')->status)->toBe(RowStatus::Valid)
        ->and(exportRow($import, '60')->status)->not->toBe(RowStatus::Skipped);
});

test('weights read with a dot and prices with the decimal comma', function () {
    $import = stageRowTypeExport();

    $small = exportRow($import, '58')->effectiveRow();
    $jacket = exportRow($import, '60')->effectiveRow();

    expect($small->get('variant.weight'))->toBe(1.25)
        ->and($small->get('variant.price'))->toBe(125659)
        ->and($jacket->get('variant.weight'))->toBe(1.25)
        ->and($jacket->get('variant.price'))->toBe(125659)
        ->and($jacket->get('variant.sale_price'))->toBe(99900)
        ->and(exportRow($import, '59')->effectiveRow()->get('variant.weight'))->toBe(0.5)
        ->and($import->sheets()->sole()->sheetMapping()->weightUnit)->toBe('kg');
});

test('the escape apostrophe, literal line breaks, and entities in brand names are cleaned', function () {
    $import = stageRowTypeExport();

    expect(exportRow($import, '82')->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Ofertă', 'value' => '-20%']])
        ->and($import->values()->where('type', ValueType::OptionValue)->where('source_value', '-20%')->exists())->toBeTrue()
        ->and(exportRow($import, '57')->effectiveRow()->get('product.description.ro'))->toBe("Rochie lejeră.\nBumbac 100%.")
        ->and($import->values()->where('type', ValueType::Brand)->pluck('source_value')->sort()->values()->all())->toBe(['Dolce & Gabbana', 'Furnizor Textil']);
});

test('the attributes of single products and the plain attributes of parent rows are properties', function () {
    $import = stageRowTypeExport();

    expect($import->values()->where('type', ValueType::Property)->pluck('source_value')->sort()->values()->all())->toBe(['Culoare', 'Material'])
        ->and($import->values()->where('type', ValueType::Option)->pluck('source_value')->sort()->values()->all())->toBe(['Mărime', 'Ofertă'])
        ->and(exportRow($import, '10')->effectiveRow()->get('variant.shippable'))->toBeFalse()
        ->and(exportRow($import, '60')->effectiveRow()->get('variant.shippable'))->toBeNull();
});

test('status 2 gives a draft without a warning, and backorder makes the variant purchasable on backorder', function () {
    Http::fake(fn () => Http::response('', 404));

    $import = stageRowTypeExport();

    $messages = ProductImportRow::includedIn($import)->get()->flatMap(fn (ProductImportRow $row) => collect($row->messages)->pluck('code'));

    expect(exportRow($import, '57')->effectiveRow()->get('product.status'))->toBe('draft')
        ->and($messages)->not->toContain('unknown_status')
        ->and(exportRow($import, '58')->effectiveRow()->get('variant.stock_status'))->toBe('on_backorder')
        ->and(exportRow($import, '58')->effectiveRow()->get('variant.backorders'))->toBeTrue();

    ImportBuilder::commit($import);

    $dress = Product::query()->whereHas('variants', fn ($query) => $query->where('sku', 'RV-57-S'))->sole();

    expect($dress->status)->toBe('draft')
        ->and($dress->variants()->pluck('sku')->sort()->values()->all())->toBe(['RV-57-M', 'RV-57-S'])
        ->and(ProductVariant::query()->where('sku', 'RV-57-S')->value('purchasable'))->toBe('in_stock_or_on_backorder')
        ->and(ProductVariant::query()->where('sku', 'CARD-10')->value('shippable'))->toBeFalsy()
        ->and(ProductVariant::query()->where('sku', 'GP-60')->value('shippable'))->toBeTruthy()
        ->and(Product::query()->whereHas('variants', fn ($query) => $query->where('sku', 'GP-60'))->value('status'))->toBe('published');
});

test('the export with hungarian headers maps the same way', function () {
    $english = stageRowTypeExport('en');
    $hungarian = stageRowTypeExport('hu');

    $summary = fn (ProductImport $import): array => ProductImportRow::includedIn($import)->orderBy('row_number')->get()
        ->mapWithKeys(fn (ProductImportRow $row): array => [$row->source_id => [
            'row' => $row->row_number,
            'role' => $row->role,
            'status' => $row->status,
            'values' => collect($row->effectiveRow()->values)->sortKeys()->all(),
            'messages' => collect($row->messages)->pluck('code')->all(),
        ]])
        ->all();

    $hungarianSheet = $hungarian->sheets()->sole()->sheetMapping();

    expect($summary($hungarian))->toBe($summary($english))
        ->and($hungarian->values()->count())->toBe($english->values()->count())
        ->and($hungarianSheet->column(35)->header)->toBe('Tulajdonság neve 1')
        ->and($hungarianSheet->column(35)->target)->toBe('variant.option_name')
        ->and($hungarianSheet->column(39)->header)->toBe('Tulajdonság neve 1')
        ->and($hungarianSheet->column(39)->target)->toBe('ignore');
});

test('the sale price next to the price imports the price and warns with the sale price and its dates', function () {
    Http::fake(fn () => Http::response('', 404));

    $import = stageRowTypeExport();
    $jacket = exportRow($import, '60');

    expect(collect($jacket->messages)->where('level', 'warning')->pluck('message')->all())
        ->toContain('The price 1256.59 was imported. The file also has the sale price 999.00 (from 1 November 2026 until 24 December 2026). Correct the price to use it.')
        ->and($import->values()->where('type', ValueType::Discount)->exists())->toBeFalse();

    ImportBuilder::commit($import);

    $price = ProductVariant::query()->where('sku', 'GP-60')->sole()->basePrices()->sole();

    expect($price->price->value)->toBe(125659)
        ->and($price->getRawOriginal('compare_price'))->toBeNull();
});
