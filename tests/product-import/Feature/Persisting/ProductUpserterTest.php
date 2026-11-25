<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\DiscountManagerInterface;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Discountable;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\Discounts;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    $this->currency = Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();
    $this->group = CollectionGroup::factory()->create();

    $this->femei = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Femei')])]);
    $this->tricouri = Collection::factory()->make(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Tricouri')])]);
    $this->femei->appendNode($this->tricouri);
    $this->noutati = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Noutăți')])]);
});

/**
 * An import of a full product sheet, staged and ready to write.
 *
 * @param  list<list<?string>>  $rows  [SKU, group, name, price, stock, size, color, category]
 */
function fullImport(int $productTypeId, int $groupId, array $rows, ?ImportOptions $options = null): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging, [
        'options' => ($options ?? new ImportOptions(collectionGroupId: $groupId))->toArray(),
    ]);

    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Stoc', 'Marime', 'Culoare', 'Categorie'], ...$rows]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price',
        4 => 'variant.stock', 5 => 'variant.option', 6 => 'variant.option', 7 => 'product.category',
    ], $productTypeId, 1);

    return ImportBuilder::stage($import);
}

function existingProduct(string $sku, int $price = 10000, int $stock = 5, string $name = 'Produs vechi'): ProductVariant
{
    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => collect(['name' => new TranslatedText(collect(['ro' => new Text($name)]))]),
    ]);

    $variant = ProductVariant::factory()->for($product, 'product')->create(['sku' => $sku, 'stock' => $stock]);
    $variant->prices()->create(['currency_id' => Currency::getDefault()->id, 'min_quantity' => 1, 'price' => $price]);

    return $variant;
}

test('tricou basic becomes one draft product with three variants, options, prices, and stock', function () {
    $import = fullImport($this->productType->id, $this->group->id, [
        ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '12', 'S', 'Alb', 'Femei > Tricouri'],
        ['TB-001-M', 'TB-001', 'Tricou basic', '49,90', '8', 'M', 'Alb', 'Femei > Tricouri'],
        ['TB-001-L', 'TB-001', 'Tricou basic', '54,90', '0', 'L', 'Alb', 'Femei > Tricouri'],
    ]);

    ImportBuilder::commit($import);

    $product = Product::query()->sole();
    $variants = $product->variants()->orderBy('sku')->get();

    expect($product->status)->toBe('draft')
        ->and($product->product_type_id)->toBe($this->productType->id)
        ->and($product->translateAttribute('name', 'ro'))->toBe('Tricou basic')
        ->and($product->collections->pluck('id')->all())->toBe([$this->tricouri->id])
        ->and($product->productOptions->pluck('handle')->sort()->values()->all())->toBe(['culoare', 'marime'])
        ->and($variants->pluck('sku')->all())->toBe(['TB-001-L', 'TB-001-M', 'TB-001-S'])
        ->and($variants->pluck('stock')->all())->toBe([0, 8, 12])
        ->and($variants->map(fn (ProductVariant $variant) => $variant->basePrices->first()->price->value)->all())->toBe([5490, 4990, 4990])
        ->and($variants->first()->values->map(fn ($value) => $value->translate('name'))->sort()->values()->all())->toBe(['Alb', 'L'])
        ->and($variants->first()->tax_class_id)->toBe(TaxClass::getDefault()->id)
        ->and(ProductImportRow::where('status', RowStatus::Imported)->count())->toBe(3)
        ->and(ProductImportRow::where('row_number', 2)->first()->product_id)->toBe($product->id);
});

test('a variant gets its weight in the unit of its sheet', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Greutate'], ['TB-001-S', 'Tricou basic', '49,90', '250']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'variant.weight'], $this->productType->id, weightUnit: 'g');
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $variant = ProductVariant::query()->sole();

    expect((float) $variant->weight_value)->toBe(250.0)
        ->and($variant->weight_unit)->toBe('g');
});

/**
 * A staged import of one-variant products with a "Published" column.
 *
 * @param  list<list<?string>>  $rows  [SKU, name, price, published]
 */
function statusImport(int $productTypeId, array $rows, NewProductStatus $status): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging, ['options' => (new ImportOptions(newProductStatus: $status))->toArray()]);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Published'], ...$rows]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.status'], $productTypeId);

    return ImportBuilder::stage($import);
}

test('with the status column option new products take their status value', function () {
    $import = statusImport($this->productType->id, [
        ['TB-001', 'Tricou basic', '49,90', '1'],
        ['RV-001', 'Rochie vară', '129', '-1'],
        ['GC-001', 'Geacă', '199', 'maybe'],
    ], NewProductStatus::FromColumn);

    ImportBuilder::commit($import);

    expect(ProductVariant::where('sku', 'TB-001')->sole()->product->status)->toBe('published')
        ->and(ProductVariant::where('sku', 'RV-001')->sole()->product->status)->toBe('draft')
        ->and(ProductVariant::where('sku', 'GC-001')->sole()->product->status)->toBe('draft');
});

test('with the draft option a published status value still gives a draft', function () {
    ImportBuilder::commit(statusImport($this->productType->id, [['TB-001', 'Tricou basic', '49,90', '1']], NewProductStatus::Draft));

    expect(Product::query()->sole()->status)->toBe('draft');
});

test('the status of an existing product never changes', function () {
    $variant = existingProduct('HO-114-M');
    $variant->product->update(['status' => 'draft']);

    ImportBuilder::commit(statusImport($this->productType->id, [['HO-114-M', 'Hanorac', '149', '1']], NewProductStatus::FromColumn));

    expect($variant->product->fresh()->status)->toBe('draft');
});

test('a product is added to every category of its list', function () {
    $import = fullImport($this->productType->id, $this->group->id, [
        ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '12', 'S', 'Alb', 'Femei > Tricouri, Noutăți'],
    ]);

    ImportBuilder::commit($import);

    expect(Product::query()->sole()->collections->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->tricouri->id, $this->noutati->id])->sort()->values()->all());
});

test('variation rows without categories take the categories of the group', function () {
    $import = fullImport($this->productType->id, $this->group->id, [
        ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '12', 'S', 'Alb', 'Femei > Tricouri'],
        ['TB-001-M', 'TB-001', 'Tricou basic', '49,90', '8', 'M', 'Alb', ''],
        ['TB-001-L', 'TB-001', 'Tricou basic', '49,90', '3', 'L', 'Alb', null],
    ]);

    expect($import->stats['review']['products_without_category'])->toBe(0)
        ->and(ProductImportRow::query()->get()->flatMap(fn (ProductImportRow $row) => collect($row->messages)->pluck('code'))->all())->not->toContain('no_category');

    ImportBuilder::commit($import);

    expect(Product::query()->sole()->collections->pluck('id')->all())->toBe([$this->tricouri->id]);
});

test('every created and updated product is added to the additional collection', function () {
    $all = Collection::factory()->create(['collection_group_id' => CollectionGroup::factory()->create()->id, 'attribute_data' => collect(['name' => new Text('Toate categoriile')])]);
    $rows = [];

    foreach (range(1, 13) as $i) {
        existingProduct("EX-{$i}")->product->collections()->attach($this->noutati->id, ['position' => 7]);
        $rows[] = ["EX-{$i}", "EX-{$i}", "Produs {$i}", '1', '99', 'S', 'Alb', 'Femei > Tricouri'];
    }

    foreach (range(1, 21) as $i) {
        $rows[] = ["NEW-{$i}", "NEW-{$i}", "Produs nou {$i}", '20', '3', 'S', 'Alb', 'Femei > Tricouri'];
    }

    $import = fullImport($this->productType->id, $this->group->id, $rows, new ImportOptions(
        collectionGroupId: $this->group->id,
        additionalCollectionIds: [$all->id],
    ));

    ImportBuilder::commit($import);

    $products = Product::query()->with('collections')->get();

    expect($products)->toHaveCount(34)
        ->and($products->every(fn (Product $product) => $product->collections->pluck('id')->contains($all->id)
            && $product->collections->pluck('id')->contains($this->tricouri->id)))->toBeTrue()
        ->and($products->filter(fn (Product $product) => $product->collections->pluck('id')->contains($this->noutati->id))->count())->toBe(13)
        ->and($products->first()->collections->firstWhere('id', $this->noutati->id)->pivot->position)->toBe(7);
});

test('a product left unchanged by skip-existing is not added to the additional collection', function () {
    $all = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Toate categoriile')])]);
    $variant = existingProduct('EX-1');

    $import = fullImport($this->productType->id, $this->group->id, [
        ['EX-1', 'EX-1', 'Produs vechi', '1', '99', 'S', 'Alb', 'Femei > Tricouri'],
        ['NEW-1', 'NEW-1', 'Produs nou', '20', '3', 'S', 'Alb', 'Femei > Tricouri'],
    ], new ImportOptions(existingVariants: ExistingVariants::Skip, collectionGroupId: $this->group->id, additionalCollectionIds: [$all->id]));

    ImportBuilder::commit($import);

    expect($variant->product->fresh()->collections)->toBeEmpty()
        ->and(ProductVariant::where('sku', 'NEW-1')->sole()->product->collections->pluck('id')->all())->toContain($all->id);
});

test('an additional collection deleted before the import is ignored', function () {
    $deleted = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Sters')])]);

    $import = fullImport($this->productType->id, $this->group->id, [
        ['NEW-1', 'NEW-1', 'Produs nou', '20', '3', 'S', 'Alb', 'Femei > Tricouri'],
    ], new ImportOptions(collectionGroupId: $this->group->id, additionalCollectionIds: [$deleted->id, $this->noutati->id]));

    $deleted->delete();

    ImportBuilder::commit($import);

    expect(ProductImportRow::where('status', RowStatus::Imported)->count())->toBe(1)
        ->and(Product::query()->sole()->collections->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->tricouri->id, $this->noutati->id])->sort()->values()->all());
});

test('a stock-only sheet changes only stock', function () {
    $variant = existingProduct('TB-001-S', 10000, 5, 'Nume vechi');
    $variant->product->collections()->attach($this->noutati->id, ['position' => 1]);

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Stoc', [['Cod', 'Stoc'], ['TB-001-S', '42']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => 'variant.stock'], $this->productType->id);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $variant->refresh();

    expect($variant->stock)->toBe(42)
        ->and($variant->basePrices->first()->price->value)->toBe(10000)
        ->and($variant->product->translateAttribute('name', 'ro'))->toBe('Nume vechi')
        ->and($variant->product->collections->pluck('id')->all())->toBe([$this->noutati->id])
        ->and(ProductImportRow::where('row_number', 2)->sole()->action)->toBe(RowAction::Update);
});

test('an existing collection is kept when a category is added', function () {
    $variant = existingProduct('TB-001-S');
    $variant->product->collections()->attach($this->noutati->id, ['position' => 1]);

    $import = fullImport($this->productType->id, $this->group->id, [
        ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '12', 'S', 'Alb', 'Femei > Tricouri'],
    ]);

    ImportBuilder::commit($import);

    expect($variant->product->fresh()->collections->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->noutati->id, $this->tricouri->id])->sort()->values()->all());
});

test('a trashed product is restored', function () {
    $variant = existingProduct('OLD-1');
    $variant->product->delete();

    expect(Product::find($variant->product_id))->toBeNull();

    $import = fullImport($this->productType->id, $this->group->id, [
        ['OLD-1', 'OLD', 'Produs vechi', '10', '1', 'S', 'Alb', 'Femei > Tricouri'],
    ]);

    ImportBuilder::commit($import);

    expect(Product::find($variant->product_id))->not->toBeNull()
        ->and(ProductVariant::find($variant->id))->not->toBeNull();
});

test('a sku created after the preview is updated, not duplicated', function () {
    $import = fullImport($this->productType->id, $this->group->id, [
        ['NEW-1', 'NEW', 'Produs nou', '20', '3', 'S', 'Alb', 'Femei > Tricouri'],
    ]);

    expect(ProductImportRow::where('row_number', 2)->sole()->action)->toBe(RowAction::Create);

    // Another import created the SKU meanwhile.
    $created = existingProduct('NEW-1', 1500, 1);

    ImportBuilder::commit($import);

    expect(ProductVariant::where('sku', 'NEW-1')->count())->toBe(1)
        ->and($created->fresh()->stock)->toBe(3)
        ->and($created->fresh()->basePrices->first()->price->value)->toBe(2000)
        ->and(ProductImportRow::where('row_number', 2)->sole()->action)->toBe(RowAction::Update)
        ->and(Product::count())->toBe(1);
});

test('skip-existing leaves 13 matched groups unchanged', function () {
    $rows = [];

    foreach (range(1, 13) as $i) {
        existingProduct("EX-{$i}", 10000, 5);
        $rows[] = ["EX-{$i}", "EX-{$i}", "Produs {$i}", '1', '99', 'S', 'Alb', 'Femei > Tricouri'];
    }

    $rows[] = ['NEW-1', 'NEW-1', 'Produs nou', '20', '3', 'S', 'Alb', 'Femei > Tricouri'];

    $import = fullImport($this->productType->id, $this->group->id, $rows, new ImportOptions(
        existingVariants: ExistingVariants::Skip,
        collectionGroupId: $this->group->id,
    ));

    $prices = Price::query()->orderBy('id')->pluck('price')->map(fn ($price) => $price->value)->all();

    ImportBuilder::commit($import);

    expect(ProductVariant::where('sku', 'like', 'EX-%')->pluck('stock')->unique()->all())->toBe([5])
        ->and(Price::query()->orderBy('id')->limit(13)->pluck('price')->map(fn ($price) => $price->value)->all())->toBe($prices)
        ->and(Product::query()->whereHas('collections')->count())->toBe(1)
        ->and(ProductImportRow::where('status', RowStatus::Skipped)->count())->toBe(13)
        ->and(ProductImportRow::where('status', RowStatus::Imported)->count())->toBe(1);
});

test('tags are added to the existing ones, and a decided property fills its attribute', function () {
    $material = Attribute::factory()->create(['handle' => 'material', 'attribute_type' => 'product', 'type' => TranslatedText::class, 'name' => ['ro' => 'Material']]);
    $this->productType->mappedAttributes()->attach([$material->id]);

    $existing = existingProduct('RV-1');
    $existing->product->tags()->attach(Tag::query()->create(['value' => 'NOU'])->id);

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Etichete', 'Proprietati'],
        ['RV-1', 'Rochie vară', '199,00', 'vara, bumbac, nou', 'Material:Bumbac, Compoziție:Bumbac 100%'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.tags', 4 => 'product.properties'], $this->productType->id);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $product = $existing->product->fresh();

    expect($product->tags()->pluck('value')->sort()->values()->all())->toBe(['BUMBAC', 'NOU', 'VARA'])
        ->and($product->translateAttribute('material', 'ro'))->toBe('Bumbac')
        ->and($product->attribute_data->has('compozitie'))->toBeFalse();
});

test('backorders and the stock status set how a variant can be bought', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['SKU', 'Name', 'Regular price', 'Stock', 'In stock?', 'Backorders allowed?'],
        ['BO-1', 'Rochie', '199,00', '0', '0', 'notify'],
        ['AL-1', 'Bluza', '99,00', '', '1', '0'],
        ['OUT-1', 'Fusta', '89,00', '', '0', '0'],
        ['IN-1', 'Geaca', '299,00', '4', '1', '0'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => ['variant.stock', null, ColumnFormat::number('.', '')],
        4 => 'variant.stock_status', 5 => ['variant.backorders', null, ColumnFormat::meanings(['notify' => 'yes'])],
    ], $this->productType->id);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $purchasable = ProductVariant::query()->pluck('purchasable', 'sku')->all();

    expect($purchasable)->toBe(['BO-1' => 'in_stock_or_on_backorder', 'AL-1' => 'always', 'OUT-1' => 'in_stock', 'IN-1' => 'in_stock'])
        ->and(ProductVariant::query()->where('sku', 'OUT-1')->value('stock'))->toBe(0);
});

test('a variant gets its identifiers, minimum quantity, shippability, and dimensions in the unit of its sheet', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'GTIN', 'MPN', 'Cantitate minima', 'Livrabil', 'Lungime', 'Latime', 'Inaltime'],
        ['GP-60', 'Geacă puf', '1256,59', '05941234567890', 'MPN-60', '2', 'nu', '60', '45', '5,5'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'variant.gtin', 4 => 'variant.mpn',
        5 => 'variant.min_quantity', 6 => 'variant.shippable', 7 => 'variant.length', 8 => 'variant.width', 9 => 'variant.height',
    ], $this->productType->id, lengthUnit: 'cm');
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $variant = ProductVariant::query()->sole();

    expect($variant->gtin)->toBe('05941234567890')
        ->and($variant->mpn)->toBe('MPN-60')
        ->and($variant->min_quantity)->toBe(2)
        ->and((bool) $variant->shippable)->toBeFalse()
        ->and((float) $variant->length_value)->toBe(60.0)
        ->and($variant->length_unit)->toBe('cm')
        ->and((float) $variant->width_value)->toBe(45.0)
        ->and($variant->width_unit)->toBe('cm')
        ->and((float) $variant->height_value)->toBe(5.5)
        ->and($variant->height_unit)->toBe('cm');
});

test('the image urls of a group are recorded in file order, the variant images linked to their variants', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Marime', 'Imagini', 'Imagine varianta'],
        ['TB-S', 'TB', 'Tricou', '49,90', 'S', 'https://img.example.com/a.jpg, https://img.example.com/b.jpg', 'https://img.example.com/s.jpg'],
        ['TB-M', 'TB', 'Tricou', '49,90', 'M', 'https://img.example.com/b.jpg, ftp://img.example.com/c.jpg', ''],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price', 4 => 'variant.option', 5 => 'product.images', 6 => 'variant.image'], $this->productType->id, 1);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $small = ProductVariant::query()->where('sku', 'TB-S')->sole();
    $images = ProductImportImage::query()->orderBy('position')->get();

    expect($images->map(fn (ProductImportImage $image) => [$image->url, $image->product_variant_id, $image->status])->all())->toBe([
        ['https://img.example.com/a.jpg', null, ImageStatus::Pending],
        ['https://img.example.com/b.jpg', null, ImageStatus::Pending],
        ['https://img.example.com/s.jpg', $small->id, ImageStatus::Pending],
    ])
        ->and($images->pluck('product_id')->unique()->all())->toBe([$small->product_id]);
});

test('with variant images linked, a variant row\'s product images are linked to its variant, and the product row and a product with one variant keep theirs', function (bool $linkVariantImages, array $expected) {
    $import = ImportBuilder::import(ImportStatus::Staging, [
        'options' => (new ImportOptions(collectionGroupId: $this->group->id, linkVariantImages: $linkVariantImages))->toArray(),
    ]);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['ID', 'Parinte', 'Cod', 'Denumire', 'Pret', 'Marime', 'Imagini'],
        ['1', '', 'TB', 'Tricou', '', '', 'https://img.example.com/a.jpg, https://img.example.com/b.jpg'],
        ['2', 'id:1', 'TB-S', 'Tricou - S', '49,90', 'S', 'https://img.example.com/a.jpg'],
        ['3', 'id:1', 'TB-M', 'Tricou - M', '49,90', 'M', 'https://img.example.com/c.jpg'],
        ['4', '', 'GP', 'Geacă', '99,00', '', 'https://img.example.com/d.jpg'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'source.parent', 2 => 'variant.sku', 3 => ['product.name', 'ro'],
        4 => 'variant.price', 5 => 'variant.option', 6 => 'product.images',
    ], $this->productType->id, strategy: GroupingStrategy::ByParent);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $skus = ProductVariant::query()->pluck('sku', 'id');

    expect(ProductImportImage::query()->orderBy('product_id')->orderBy('position')->get()
        ->map(fn (ProductImportImage $image) => [$image->url, $skus[$image->product_variant_id] ?? null])->all())
        ->toBe($expected);
})->with([
    'linked' => [true, [
        ['https://img.example.com/a.jpg', null],
        ['https://img.example.com/b.jpg', null],
        ['https://img.example.com/a.jpg', 'TB-S'],
        ['https://img.example.com/c.jpg', 'TB-M'],
        ['https://img.example.com/d.jpg', null],
    ]],
    'not linked' => [false, [
        ['https://img.example.com/a.jpg', null],
        ['https://img.example.com/b.jpg', null],
        ['https://img.example.com/c.jpg', null],
        ['https://img.example.com/d.jpg', null],
    ]],
]);

/**
 * Import one sheet with a Romanian URL slug column and write it.
 *
 * @param  list<list<string>>  $rows  SKU, name, price, slug.
 */
function importWithSlugs(int $productTypeId, array $rows, bool $mapsSlug = true): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Denumire', 'Pret', 'URL key'], ...$rows]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => $mapsSlug ? ['product.slug', 'ro'] : null], $productTypeId);
    ImportBuilder::stage($import);

    return ImportBuilder::commit($import);
}

/**
 * The Romanian URL slugs of a product, the default first.
 *
 * @return list<string>
 */
function romanianSlugs(Product $product): array
{
    return $product->urls()->where('language_id', Language::getDefault()->id)->orderByDesc('default')->orderBy('id')->pluck('slug')->all();
}

test('a new product with a slug gets it as its default url instead of the generated one', function () {
    importWithSlugs($this->productType->id, [['RV-1', 'Rochie de vară', '199,00', 'rochie-vara']]);

    $product = ProductVariant::where('sku', 'RV-1')->first()->product;

    expect(romanianSlugs($product))->toBe(['rochie-vara'])
        ->and($product->defaultUrl->slug)->toBe('rochie-vara');
});

test('a new product without a slug column keeps the url generated from its name', function () {
    importWithSlugs($this->productType->id, [['RV-1', 'Rochie de vară', '199,00', 'rochie-vara']], mapsSlug: false);

    expect(romanianSlugs(ProductVariant::where('sku', 'RV-1')->first()->product))->toBe(['rochie-de-vara']);
});

test('an existing product keeps its url', function () {
    $existing = existingProduct('HO-114-M')->product;
    $existing->urls()->delete();
    $existing->urls()->create(['language_id' => Language::getDefault()->id, 'slug' => 'rochie-de-vara', 'default' => true]);

    importWithSlugs($this->productType->id, [['HO-114-M', 'Rochie vară', '199,00', 'rochie-vara']]);

    expect(romanianSlugs($existing->fresh()))->toBe(['rochie-de-vara']);
});

test('an existing product without a url in the language gets the slug', function () {
    $existing = existingProduct('HO-114-M')->product;
    $existing->urls()->delete();

    importWithSlugs($this->productType->id, [['HO-114-M', 'Rochie vară', '199,00', 'rochie-vara']]);

    expect(romanianSlugs($existing->fresh()))->toBe(['rochie-vara']);
});

test('a slug that another product holds gets a number suffix', function () {
    $other = existingProduct('OTHER-1')->product;
    $other->urls()->delete();
    $other->urls()->create(['language_id' => Language::getDefault()->id, 'slug' => 'rochie-vara', 'default' => true]);

    importWithSlugs($this->productType->id, [['RV-1', 'Rochie de vară', '199,00', 'rochie-vara']]);

    expect(romanianSlugs(ProductVariant::where('sku', 'RV-1')->first()->product))->toBe(['rochie-vara-2'])
        ->and(romanianSlugs($other->fresh()))->toBe(['rochie-vara']);
});

test('the meta title, description, and keywords are written into their attributes in romanian', function () {
    $seo = AttributeGroup::factory()->create(['handle' => 'seo', 'name' => ['en' => 'Search Engine Optimization']]);
    $attributes = collect(['product-meta-title' => 'Title', 'product-meta-description' => 'Description', 'product-meta-keywords' => 'Keywords'])
        ->map(fn (string $name, string $handle) => Attribute::factory()->create([
            'handle' => $handle, 'attribute_type' => 'product', 'type' => TranslatedText::class,
            'name' => ['en' => $name], 'attribute_group_id' => $seo->id,
        ]));
    $this->productType->mappedAttributes()->attach($attributes->pluck('id')->all());

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Meta titlu', 'Meta descriere', 'Cuvinte cheie'],
        ['RV-1', 'Rochie vară', '199,00', 'Rochie de vară albă', 'Rochie lejeră din bumbac', 'rochie, vara, bumbac'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price',
        3 => ['attribute.product-meta-title', 'ro'], 4 => ['attribute.product-meta-description', 'ro'], 5 => ['attribute.product-meta-keywords', 'ro'],
    ], $this->productType->id);
    ImportBuilder::stage($import);
    ImportBuilder::commit($import);

    $product = ProductVariant::where('sku', 'RV-1')->first()->product;

    expect($product->translateAttribute('product-meta-title', 'ro'))->toBe('Rochie de vară albă')
        ->and($product->translateAttribute('product-meta-description', 'ro'))->toBe('Rochie lejeră din bumbac')
        ->and($product->translateAttribute('product-meta-keywords', 'ro'))->toBe('rochie, vara, bumbac');
});

test('a sale price is not written, the price has no compare price, and an existing compare price stays', function () {
    $existing = existingProduct('SALE-2', 24900);
    $existing->basePrices()->first()->update(['compare_price' => 24900]);

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Pret promotional'],
        ['SALE-1', 'Rochie', '199,00', '149,00'],
        ['SALE-2', 'Bluza', '199,00', '149,00'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'variant.sale_price'], $this->productType->id);
    ImportBuilder::stage($import);

    ImportBuilder::commit($import);

    $new = ProductVariant::query()->where('sku', 'SALE-1')->sole()->basePrices()->sole();
    $updated = $existing->fresh()->basePrices()->sole();

    expect($new->price->value)->toBe(19900)
        ->and($new->getRawOriginal('compare_price'))->toBeNull()
        ->and($updated->price->value)->toBe(19900)
        ->and($updated->compare_price->value)->toBe(24900);
});

/**
 * The outlet sheet: two dresses at 229.00 with 30% and a bag at 59.00 with
 * 50%, all until the end of 31 December 2026, staged on 24 November 2026.
 */
function outletImport(int $productTypeId): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);

    Discounts::sheet($import, $productTypeId, [
        2 => ['OUT-RUHA-S', 'Rochie S', '229,00', '', '30', '', '2026-12-31'],
        3 => ['OUT-RUHA-M', 'Rochie M', '229,00', '', '30', '', '2026-12-31'],
        4 => ['OUT-TASKA', 'Geantă', '59,00', '', '50', '', '2026-12-31'],
    ]);

    return ImportBuilder::stage($import);
}

/**
 * The SKUs of the variants a discount is limited to.
 *
 * @return list<string>
 */
function limitedSkus(Discount $discount): array
{
    return ProductVariant::query()
        ->whereIn('id', $discount->discountables()->where('type', 'limitation')->where('discountable_type', (new ProductVariant)->getMorphClass())->pluck('discountable_id'))
        ->orderBy('sku')
        ->pluck('sku')
        ->all();
}

test('variants are added to the discounts created for them and keep their prices without a compare price', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00'));

    $import = outletImport($this->productType->id);

    ImportBuilder::commit($import);

    $thirty = Discount::query()->find($import->values()->where('normalized_key', 'percent:30||2026-12-31 23:59:59')->value('target_id'));
    $fifty = Discount::query()->find($import->values()->where('normalized_key', 'percent:50||2026-12-31 23:59:59')->value('target_id'));
    $prices = ProductVariant::query()->orderBy('sku')->get()->mapWithKeys(fn (ProductVariant $variant): array => [
        $variant->sku => [$variant->basePrices()->sole()->price->value, $variant->basePrices()->sole()->getRawOriginal('compare_price')],
    ])->all();

    expect($prices)->toBe(['OUT-RUHA-M' => [22900, null], 'OUT-RUHA-S' => [22900, null], 'OUT-TASKA' => [5900, null]])
        ->and(limitedSkus($thirty))->toBe(['OUT-RUHA-M', 'OUT-RUHA-S'])
        ->and(limitedSkus($fifty))->toBe(['OUT-TASKA'])
        ->and($thirty->ends_at->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59')
        ->and($fifty->ends_at->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59');
});

test('using an existing discount adds the variants to it and changes nothing else on it', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00'));

    $limited = collect(range(1, 4))->map(fn (int $i): ProductVariant => existingProduct("IARNA-{$i}"))->all();
    $iarna = Discounts::percent('Iarna', 30, '2026-11-20 00:00:00', '2026-12-31 23:59:59', $limited);
    $channels = $iarna->channels()->get()->map(fn ($channel): array => [$channel->id, (bool) $channel->pivot->enabled])->all();
    $groups = $iarna->customerGroups()->get()->map(fn ($group): array => [$group->id, (bool) $group->pivot->enabled])->all();
    $before = $iarna->fresh()->only(['name', 'handle', 'data', 'starts_at', 'ends_at', 'coupon', 'priority']);

    $import = outletImport($this->productType->id);
    $import->values()->where('normalized_key', 'percent:50||2026-12-31 23:59:59')->update(['action' => ValueAction::Skip->value]);

    ImportBuilder::commit($import);

    $iarna->refresh();

    expect($import->values()->where('normalized_key', 'percent:30||2026-12-31 23:59:59')->value('target_id'))->toBe($iarna->id)
        ->and(limitedSkus($iarna))->toBe(['IARNA-1', 'IARNA-2', 'IARNA-3', 'IARNA-4', 'OUT-RUHA-M', 'OUT-RUHA-S'])
        ->and($iarna->only(['name', 'handle', 'data', 'starts_at', 'ends_at', 'coupon', 'priority']))->toEqual($before)
        ->and($iarna->channels()->get()->map(fn ($channel): array => [$channel->id, (bool) $channel->pivot->enabled])->all())->toBe($channels)
        ->and($iarna->customerGroups()->get()->map(fn ($group): array => [$group->id, (bool) $group->pivot->enabled])->all())->toBe($groups)
        ->and(Discount::count())->toBe(1)
        ->and(Discountable::query()->where('discountable_id', ProductVariant::query()->where('sku', 'OUT-TASKA')->value('id'))->exists())->toBeFalse();
});

test('after the import the shop finds the discount of a variant', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00'));

    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    $import = outletImport($this->productType->id);

    ImportBuilder::commit($import);

    $variant = ProductVariant::query()->where('sku', 'OUT-RUHA-S')->sole();
    $discount = app(DiscountManagerInterface::class)->getDiscountForPurchasable($variant);

    expect($discount?->id)->toBe($import->values()->where('normalized_key', 'percent:30||2026-12-31 23:59:59')->value('target_id'))
        ->and($discount->data['percentage'])->toBe(30);
});
