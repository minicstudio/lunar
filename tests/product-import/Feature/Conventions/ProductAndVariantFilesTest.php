<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToStaging;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueAction;
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
    ColumnMappingAgent::fake(fn (string $prompt) => str_contains($prompt, '"sheet":"variants.csv"')
        ? ExportFixtures::variantsAnswer()
        : ExportFixtures::productsAnswer());
});

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Upload the products file and the variants file as one import, read and
 * analyze them with the faked answers, and stage them.
 */
function stageProductAndVariantFiles(?ImportOptions $options = null): ProductImport
{
    $import = ImportBuilder::uploaded(['products.csv' => ExportFixtures::products(), 'variants.csv' => ExportFixtures::variants()], [
        'options' => ($options ?? new ImportOptions(collectionGroupId: test()->group->id))->toArray(),
    ]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);

    app(ContinueToStaging::class)->handle($import->fresh());

    return $import->fresh();
}

function fileRow(ProductImport $import, string $sku): ProductImportRow
{
    return ProductImportRow::includedIn($import)->get()->sole(fn (ProductImportRow $row): bool => $row->effectiveRow()->get('variant.sku') === $sku);
}

test('the variants file gives the shirt its variants with the impacts applied', function () {
    $import = stageProductAndVariantFiles();

    $shirt = ProductImportRow::includedIn($import)->where('source_id', '12')->sole();
    $small = fileRow($import, 'CAM-12-S-ALB');
    $medium = fileRow($import, 'CAM-12-M-ALB');

    expect($import->sheets()->orderBy('position')->pluck('name')->all())->toBe(['products.csv', 'variants.csv'])
        ->and($shirt->role)->toBe(RowRole::Product)
        ->and($small->role)->toBe(RowRole::Variant)
        ->and($small->group_key)->toBe($shirt->group_key)
        ->and($medium->group_key)->toBe($shirt->group_key)
        ->and($small->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Size', 'value' => 'S'], ['name' => 'Color', 'value' => 'Alb']])
        ->and($medium->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Size', 'value' => 'M'], ['name' => 'Color', 'value' => 'Alb']])
        ->and($small->effectiveRow()->get('variant.price'))->toBe(10000)
        ->and($medium->effectiveRow()->get('variant.price'))->toBe(11000)
        ->and($small->effectiveRow()->get('variant.weight'))->toBe(0.3)
        ->and($medium->effectiveRow()->get('variant.weight'))->toBe(0.3)
        ->and($small->status)->toBe(RowStatus::Valid)
        ->and($medium->status)->toBe(RowStatus::Valid);
});

test('a product without variant rows has one variant, and a variant row of a missing product has the parent error', function () {
    $import = stageProductAndVariantFiles();

    $belt = fileRow($import, 'CUR-14');
    $orphan = fileRow($import, 'X-99-L');

    expect($belt->role)->toBe(RowRole::ProductAndVariant)
        ->and($belt->status)->not->toBe(RowStatus::Error)
        ->and(ProductImportRow::includedIn($import)->where('group_key', $belt->group_key)->count())->toBe(1)
        ->and($orphan->status)->toBe(RowStatus::Error)
        ->and(collect($orphan->messages)->pluck('message')->all())->toContain('The parent "99" was not found');
});

test('features are properties, categories are paths, and the codes are read with their meanings', function () {
    $import = stageProductAndVariantFiles();

    $shirt = ProductImportRow::includedIn($import)->where('source_id', '12')->sole()->effectiveRow();
    $card = fileRow($import, 'CARD-16')->effectiveRow();
    $composition = $import->values()->where('type', ValueType::Property)->sole();

    expect($shirt->get(RowStructurer::NAMED_PROPERTIES))->toBe([['name' => 'Compoziție', 'value' => 'Bumbac 100%']])
        ->and($composition->source_value)->toBe('Compoziție')
        ->and($composition->action)->toBe(ValueAction::Skip)
        ->and($import->values()->where('type', ValueType::Category)->pluck('source_value')->sort()->values()->all())->toBe(['Accesorii', 'Cadouri', 'Femei > Rochii', 'Noutăți'])
        ->and($shirt->get('product.status'))->toBe('published')
        ->and($shirt->get('variant.backorders'))->toBeNull()
        ->and(fileRow($import, 'CUR-14')->effectiveRow()->get('variant.backorders'))->toBeTrue()
        ->and($card->get('product.status'))->toBe('draft')
        ->and($card->get('variant.backorders'))->toBeFalse()
        ->and($card->get('variant.shippable'))->toBeFalse()
        ->and(fileRow($import, 'CUR-14')->effectiveRow()->get('variant.shippable'))->toBeTrue();
});

test('a discount percent keeps the price without a compare price and links the variant to a discount', function () {
    Http::fake(fn () => Http::response('', 404));

    $import = stageProductAndVariantFiles(new ImportOptions(
        newProductStatus: NewProductStatus::FromColumn,
        collectionGroupId: test()->group->id,
    ));

    ImportBuilder::commit($import);

    $belt = ProductVariant::query()->where('sku', 'CUR-14')->sole();
    $price = $belt->basePrices()->sole();
    $discount = Discount::query()->find($import->values()->where('type', ValueType::Discount)->sole()->target_id);

    expect($price->price->value)->toBe(20000)
        ->and($price->getRawOriginal('compare_price'))->toBeNull()
        ->and($discount->data['percentage'])->toBe(25)
        ->and($discount->ends_at)->toBeNull()
        ->and($discount->discountables()->where('type', 'limitation')->pluck('discountable_id')->all())->toBe([$belt->id])
        ->and($belt->product->status)->toBe('published')
        ->and(ProductVariant::query()->where('sku', 'CARD-16')->sole()->product->status)->toBe('draft')
        ->and(ProductVariant::query()->where('sku', 'CAM-12-M-ALB')->sole()->basePrices()->sole()->price->value)->toBe(11000)
        ->and(ProductVariant::query()->where('sku', 'CAM-12-S-ALB')->sole()->product->variants()->count())->toBe(2);
});
