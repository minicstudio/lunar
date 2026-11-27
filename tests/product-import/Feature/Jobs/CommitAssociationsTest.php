<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductAssociation;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\CommitAssociations;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();

    // "Rochie vară" exists and already has an up-sell.
    $this->dress = Product::factory()->create(['attribute_data' => collect(['name' => new TranslatedText(collect(['ro' => new Text('Rochie vară')]))])]);
    ProductVariant::factory()->for($this->dress, 'product')->create(['sku' => 'RV-1']);
    $this->older = Product::factory()->create();
    $this->dress->associations()->create(['product_target_id' => $this->older->id, 'type' => ProductAssociation::UP_SELL]);

    $this->import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['ID', 'Cod', 'Grup', 'Denumire', 'Pret', 'Marime', 'Upsells', 'Cross-sells'],
        ['57', 'GP-1', 'GP', 'Geacă puf', '299,00', '', '', ''],
        ['60', 'TB-001-S', 'TB', 'Tricou basic', '49,90', 'S', 'TB-001-M', ''],
        ['61', 'TB-001-M', 'TB', 'Tricou basic', '49,90', 'M', '', ''],
        ['70', 'RV-1', 'RV', 'Rochie vară', '199,00', '', 'id:57, TB-001-S, id:310', ''],
        ['12', 'CAM-12', 'CAM', 'Cămașă', '100,00', '', '', ''],
        ['14', 'CUR-14', 'CUR', 'Curea', '200,00', '', '', ''],
        ['20', 'PAC-20', 'PAC', 'Pachet', '250,00', '', '', '12, 14'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'variant.sku', 2 => 'group_key', 3 => ['product.name', 'ro'], 4 => 'variant.price',
        5 => ['variant.option', 'Marime'], 6 => 'product.upsells', 7 => 'product.cross_sells',
    ], $this->productType->id, 2);

    ImportBuilder::stage($this->import);
    ImportBuilder::commit($this->import);
});

function runAssociations(ProductImport $import): void
{
    app()->call([new CommitAssociations($import->id), 'handle']);
}

function productWithSku(string $sku): Product
{
    return ProductVariant::query()->where('sku', $sku)->sole()->product;
}

test('up-sells name rows of the file by id and by sku, and existing associations are kept', function () {
    runAssociations($this->import);

    $upsells = $this->dress->associations()->where('type', ProductAssociation::UP_SELL)->pluck('product_target_id')->sort()->values()->all();

    expect($upsells)->toBe(collect([$this->older->id, productWithSku('GP-1')->id, productWithSku('TB-001-S')->id])->sort()->values()->all())
        ->and($this->import->fresh()->stats['associations'])->toBe(4);
});

test('accessories named by source ID become cross-sells', function () {
    runAssociations($this->import);

    expect(productWithSku('PAC-20')->associations()->where('type', ProductAssociation::CROSS_SELL)->pluck('product_target_id')->sort()->values()->all())
        ->toBe([productWithSku('CAM-12')->id, productWithSku('CUR-14')->id]);
});

test('a product is never associated with itself, and a second run adds nothing', function () {
    runAssociations($this->import);
    runAssociations($this->import);

    $shirt = productWithSku('TB-001-S');

    expect($shirt->associations()->count())->toBe(0)
        ->and(ProductAssociation::query()->count())->toBe(5);
});

test('a reference that names no product stays one warning on its row', function () {
    runAssociations($this->import);

    $messages = collect(ProductImportRow::includedIn($this->import)->where('source_id', '70')->sole()->messages);

    expect($messages->where('code', 'upsell_not_found')->pluck('message')->all())->toBe(['The up-sell "id:310" was not found']);
});
