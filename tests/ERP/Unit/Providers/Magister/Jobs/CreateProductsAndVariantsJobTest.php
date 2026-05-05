<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Collection;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\ERP\Models\ErpSyncTemp;
use Lunar\ERP\Providers\Magister\Jobs\CreateProductsAndVariantsJob;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Discountable;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
    $this->createChannel();

    if (! Language::where('default', true)->exists()) {
        Language::factory()->create([
            'code' => 'en',
            'default' => true,
            'name' => 'English',
        ]);
    }
    if (! TaxClass::first()) {
        TaxClass::factory()->create();
    }
    if (! ProductType::where('name', 'Stock')->first()) {
        ProductType::factory()->create(['name' => 'Stock']);
    }
});

test('handle creates standard product and variant when missing', function () {
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-1',
        'name' => 'Standard Product',
        'sku' => 'STD-001',
        'price' => 1000,
        'stock' => 0,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'STD-001')->first();

    expect($variant)->not->toBeNull()
        ->and($variant->product)->not->toBeNull();

    $price = $variant->prices()->first();
    $ron = Currency::firstWhere('code', 'RON');
    expect($price)->not->toBeNull()
        ->and($price->currency_id)->toBe($ron->id)
        ->and($price->price->value)->toBe(1000);
});

test('handle updates existing standard product and variant price change', function () {
    $productType = ProductType::firstWhere('name', 'Stock');
    $product = Product::create([
        'product_type_id' => $productType->id,
        'status' => 'draft',
        'attribute_data' => [
            'name' => new \Lunar\FieldTypes\TranslatedText(collect([
                'ro' => new \Lunar\FieldTypes\Text('Old Name'),
            ])),
        ],
    ]);

    $taxClass = TaxClass::first();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'erp_id' => 'ERP-2',
        'sku' => 'STD-002',
        'tax_class_id' => $taxClass->id,
    ]);

    $ron = Currency::firstWhere('code', 'RON');
    $variant->prices()->create([
        'min_quantity' => 1,
        'currency_id' => $ron->id,
        'price' => 900,
    ]);

    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-2',
        'name' => 'New Name',
        'sku' => 'STD-002',
        'price' => 1000,
        'stock' => 0,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant->refresh();
    $product->refresh();

    $price = $variant->prices()->first();
    expect($price->price->value)->toBe(1000)
        ->and($product->translateAttribute('name'))->toBe('Old Name');
});

test('handle generic product no related variants does nothing', function () {
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-GEN-0',
        'name' => 'Generic Product',
        'sku' => 'GEN-000',
        'price' => 1500,
        'stock' => 0,
        'provider_data' => ['article_kind' => 1],
        'attributes' => [
            ['NAME_TERM' => 'Color'],
        ],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    expect(Product::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});

test('handle generic product creates product, variants and options', function () {
    $color = ProductOption::factory()->create([
        'name' => ['ro' => 'Culoare'],
        'handle' => 'color',
    ]);
    $size = ProductOption::factory()->create([
        'name' => ['ro' => 'Marime'],
        'handle' => 'size',
    ]);

    $red = ProductOptionValue::factory()->create([
        'product_option_id' => $color->id,
        'name' => ['ro' => 'Red'],
    ]);
    $blue = ProductOptionValue::factory()->create([
        'product_option_id' => $color->id,
        'name' => ['ro' => 'Blue'],
    ]);
    $small = ProductOptionValue::factory()->create([
        'product_option_id' => $size->id,
        'name' => ['ro' => 'S'],
    ]);
    $medium = ProductOptionValue::factory()->create([
        'product_option_id' => $size->id,
        'name' => ['ro' => 'M'],
    ]);

    $generic = ErpSyncTemp::create([
        'erp_id' => 'ERP-GEN-1',
        'name' => 'Generic Product',
        'sku' => 'GEN-BASE',
        'price' => 0,
        'stock' => 0,
        'provider_data' => ['article_kind' => 1],
        'attributes' => [
            ['NAME_TERM' => 'Color'],
            ['NAME_TERM' => 'Size'],
        ],
    ]);

    $variant1 = ErpSyncTemp::create([
        'erp_id' => 'ERP-GEN-1-RED-S',
        'name' => 'Generic Red S',
        'sku' => 'GEN-RED-S',
        'price' => 2000,
        'stock' => 5,
        'provider_data' => ['article_kind' => 2],
        'attributes' => [
            ['NAME_TERM' => 'Red'],
            ['NAME_TERM' => 'S'],
        ],
    ]);

    $variant2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-GEN-1-BLUE-M',
        'name' => 'Generic Blue M',
        'sku' => 'GEN-BLUE-M',
        'price' => 2100,
        'stock' => 3,
        'provider_data' => ['article_kind' => 2],
        'attributes' => [
            ['NAME_TERM' => 'Blue'],
            ['NAME_TERM' => 'M'],
        ],
    ]);

    (new CreateProductsAndVariantsJob($generic, collect([$variant1, $variant2])))->handle();

    $product = Product::query()->first();
    expect($product)->not->toBeNull()
        ->and($product->productOptions()->count())->toBe(2);

    $v1 = ProductVariant::where('sku', 'GEN-RED-S')->firstOrFail();
    $v2 = ProductVariant::where('sku', 'GEN-BLUE-M')->firstOrFail();

    expect($v1->values()->count())->toBe(2)
        ->and($v1->values()->select('lunar_product_option_values.*')->get()->pluck('id')->toArray())->toEqualCanonicalizing([$red->id, $small->id]);

    expect($v2->values()->count())->toBe(2)
        ->and($v2->values()->select('lunar_product_option_values.*')->get()->pluck('id')->toArray())->toEqualCanonicalizing([$blue->id, $medium->id]);

    expect($v1->prices()->first()->price->value)->toBe(2000)
        ->and($v2->prices()->first()->price->value)->toBe(2100);
});

test('handle generic product updates existing variant and adds new', function () {
    $size = ProductOption::factory()->create([
        'name' => ['ro' => 'Marime'],
        'handle' => 'size',
    ]);
    $small = ProductOptionValue::factory()->create([
        'product_option_id' => $size->id,
        'name' => ['ro' => 'S'],
    ]);
    $large = ProductOptionValue::factory()->create([
        'product_option_id' => $size->id,
        'name' => ['ro' => 'L'],
    ]);

    $productType = ProductType::firstWhere('name', 'Stock');
    $product = Product::create([
        'product_type_id' => $productType->id,
        'status' => 'draft',
        'attribute_data' => [
            'name' => new \Lunar\FieldTypes\TranslatedText(collect([
                'ro' => new \Lunar\FieldTypes\Text('Old Generic Name'),
            ])),
        ],
    ]);
    $product->productOptions()->attach($size, ['position' => 1]);

    $taxClass = TaxClass::first();
    $existing = ProductVariant::create([
        'product_id' => $product->id,
        'erp_id' => 'ERP-EXIST',
        'sku' => 'GEN-S',
        'tax_class_id' => $taxClass->id,
    ]);
    $ron = Currency::firstWhere('code', 'RON');
    $existing->prices()->create([
        'min_quantity' => 1,
        'currency_id' => $ron->id,
        'price' => 500,
    ]);

    $generic = ErpSyncTemp::create([
        'erp_id' => 'ERP-GEN-2',
        'name' => 'New Generic Name',
        'sku' => 'GEN-BASE',
        'price' => 0,
        'stock' => 0,
        'provider_data' => ['article_kind' => 1],
        'attributes' => [['NAME_TERM' => 'Size']],
    ]);
    $variantExisting = ErpSyncTemp::create([
        'erp_id' => 'ERP-EXIST',
        'name' => 'Variant S',
        'sku' => 'GEN-S',
        'price' => 700,
        'stock' => 4,
        'provider_data' => ['article_kind' => 2],
        'attributes' => [['NAME_TERM' => 'S']],
    ]);
    $variantNew = ErpSyncTemp::create([
        'erp_id' => 'ERP-NEW-L',
        'name' => 'Variant L',
        'sku' => 'GEN-L',
        'price' => 800,
        'stock' => 2,
        'provider_data' => ['article_kind' => 2],
        'attributes' => [['NAME_TERM' => 'L']],
    ]);

    $job = new CreateProductsAndVariantsJob($generic, collect([$variantExisting, $variantNew]));
    $job->handle();

    $product->refresh();
    expect($product->translateAttribute('name'))->toBe('Old Generic Name');

    $existing->refresh();
    expect($existing->prices()->first()->price->value)->toBe(700)
        ->and($existing->values()->select('lunar_product_option_values.*')->get()->pluck('id')->toArray())->toEqualCanonicalizing([$small->id]);

    $new = ProductVariant::where('sku', 'GEN-L')->first();
    expect($new)->not->toBeNull()
        ->and($new->prices()->first()->price->value)->toBe(800)
        ->and($new->values()->select('lunar_product_option_values.*')->get()->pluck('id')->toArray())->toEqualCanonicalizing([$large->id]);

    $job->handle();
    $existing->refresh();
    $new->refresh();
    expect($existing->values()->count())->toBe(1)
        ->and($new->values()->count())->toBe(1);
});

test('variant kind alone creates nothing', function () {
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-VAR-ONLY',
        'name' => 'Variant Only',
        'sku' => 'V-ONLY',
        'price' => 1000,
        'stock' => 0,
        'provider_data' => ['article_kind' => 2],
        'attributes' => [['NAME_TERM' => 'X']],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    expect(Product::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});

test('update variant price unchanged keeps single price row', function () {
    $productType = ProductType::firstWhere('name', 'Stock');
    $product = Product::create([
        'product_type_id' => $productType->id,
        'status' => 'draft',
        'attribute_data' => [
            'name' => new \Lunar\FieldTypes\TranslatedText(collect([
                'ro' => new \Lunar\FieldTypes\Text('Name'),
            ])),
        ],
    ]);
    $taxClass = TaxClass::first();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'erp_id' => 'ERP-3',
        'sku' => 'STD-003',
        'tax_class_id' => $taxClass->id,
    ]);
    $ron = Currency::firstWhere('code', 'RON');
    $variant->prices()->create([
        'min_quantity' => 1,
        'currency_id' => $ron->id,
        'price' => 1200,
    ]);

    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-3',
        'name' => 'Name',
        'sku' => 'STD-003',
        'price' => 1200,
        'stock' => 0,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant->refresh();
    expect($variant->prices()->count())->toBe(1)
        ->and($variant->prices()->first()->price->value)->toBe(1200);
});

test('creating a product without Stock product type throws', function () {
    ProductType::where('name', 'Stock')->delete();

    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-NO-PT',
        'name' => 'No PT',
        'sku' => 'NO-PT-1',
        'price' => 1000,
        'stock' => 0,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    $job = new CreateProductsAndVariantsJob($article, Collection::make());

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Product type "Stock" not found');
    $job->handle();
});

// Discount Tests

test('creating product with discount attaches discount', function () {
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-1',
        'name' => 'Product with Discount',
        'sku' => 'DISC-001',
        'price' => 1000,
        'discount' => 10,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-001')->first();
    expect($variant)->not->toBeNull();

    $product = $variant->product;

    // Check discount was created
    $discount = Discount::where('data->percentage', 10)
        ->where('type', AdvancedAmountOff::class)
        ->first();

    expect($discount)->not->toBeNull()
        ->and($discount->name)->toBe('10% Discount')
        ->and($discount->data['percentage'])->toBe(10)
        ->and($discount->data['fixed_value'])->toBe(false);

    // Check product is attached to discount
    $discountable = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->where('discount_id', $discount->id)
        ->first();

    expect($discountable)->not->toBeNull()
        ->and($discountable->type)->toBe('limitation');
});

test('product with same discount percentage reuses existing discount', function () {
    // Create first product with 20% discount
    $article1 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-2A',
        'name' => 'Product A',
        'sku' => 'DISC-002A',
        'price' => 1000,
        'discount' => 20,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article1, Collection::make()))->handle();

    $discountCount = Discount::where('data->percentage', 20)->count();
    expect($discountCount)->toBe(1);

    // Create second product with same 20% discount
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-2B',
        'name' => 'Product B',
        'sku' => 'DISC-002B',
        'price' => 1500,
        'discount' => 20,
        'stock' => 3,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Should still have only one 20% discount
    $discountCount = Discount::where('data->percentage', 20)->count();
    expect($discountCount)->toBe(1);

    // Both products should be attached to the same discount
    $discount = Discount::where('data->percentage', 20)->first();
    $attachedProducts = Discountable::where('discount_id', $discount->id)
        ->where('discountable_type', Product::morphName())
        ->count();

    expect($attachedProducts)->toBe(2);
});

test('product with unchanged discount keeps same discount', function () {
    // Create product with 15% discount
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-3',
        'name' => 'Product C',
        'sku' => 'DISC-003',
        'price' => 1000,
        'discount' => 15,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-003')->first();
    $product = $variant->product;

    $discount = Discount::where('data->percentage', 15)->first();
    $initialDiscountableId = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->first()->id;

    // Sync same product again with same discount
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-3',
        'name' => 'Product C Updated',
        'sku' => 'DISC-003',
        'price' => 1100,
        'discount' => 15,
        'stock' => 6,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Should still have the same discountable record (not detached and reattached)
    $finalDiscountable = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->first();

    expect($finalDiscountable->id)->toBe($initialDiscountableId)
        ->and($finalDiscountable->discount_id)->toBe($discount->id);

    // Should only have one discountable for this product
    $count = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->count();

    expect($count)->toBe(1);
});

test('product with changed discount updates to new discount', function () {
    // Create product with 25% discount
    $article1 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-4',
        'name' => 'Product D',
        'sku' => 'DISC-004',
        'price' => 1000,
        'discount' => 25,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article1, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-004')->first();
    $product = $variant->product;

    $discount25 = Discount::where('data->percentage', 25)->first();
    expect($discount25)->not->toBeNull();

    // Update product to 30% discount
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-4',
        'name' => 'Product D',
        'sku' => 'DISC-004',
        'price' => 1000,
        'discount' => 30,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Should have 30% discount attached now
    $discount30 = Discount::where('data->percentage', 30)->first();
    expect($discount30)->not->toBeNull();

    $discountable = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->first();

    expect($discountable->discount_id)->toBe($discount30->id)
        ->and($discountable->discount_id)->not->toBe($discount25->id);

    // Product should only have one discount attached
    $count = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->count();

    expect($count)->toBe(1);
});

test('product with discount removed detaches discount', function () {
    // Create product with 35% discount
    $article1 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-5',
        'name' => 'Product E',
        'sku' => 'DISC-005',
        'price' => 1000,
        'discount' => 35,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article1, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-005')->first();
    $product = $variant->product;

    // Verify discount is attached
    $hasDiscount = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->exists();
    expect($hasDiscount)->toBeTrue();

    // Update product with no discount (null)
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-5',
        'name' => 'Product E',
        'sku' => 'DISC-005',
        'price' => 1000,
        'discount' => null,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Discount should be detached
    $hasDiscount = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->exists();
    expect($hasDiscount)->toBeFalse();
});

test('product with discount set to zero detaches discount', function () {
    // Create product with 40% discount
    $article1 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-6',
        'name' => 'Product F',
        'sku' => 'DISC-006',
        'price' => 1000,
        'discount' => 40,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article1, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-006')->first();
    $product = $variant->product;

    // Verify discount is attached
    $hasDiscount = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->exists();
    expect($hasDiscount)->toBeTrue();

    // Update product with 0 discount
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-6',
        'name' => 'Product F',
        'sku' => 'DISC-006',
        'price' => 1000,
        'discount' => 0,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Discount should be detached
    $hasDiscount = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->exists();
    expect($hasDiscount)->toBeFalse();
});

test('orphaned discount gets ended when last product detached', function () {
    // Create product with 50% discount
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-7',
        'name' => 'Product G',
        'sku' => 'DISC-007',
        'price' => 1000,
        'discount' => 50,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    $variant = ProductVariant::where('sku', 'DISC-007')->first();
    $product = $variant->product;

    $discount = Discount::where('data->percentage', 50)->first();
    expect($discount->ends_at)->toBeNull();

    // Remove discount from product (making discount orphaned)
    $article2 = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-7',
        'name' => 'Product G',
        'sku' => 'DISC-007',
        'price' => 1000,
        'discount' => null,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article2, Collection::make()))->handle();

    // Discount should now be ended
    $discount->refresh();
    expect($discount->ends_at)->not->toBeNull()
        ->and($discount->ends_at->isPast())->toBeTrue();
});

test('ended discount gets reactivated when reused', function () {
    // Create a discount and end it
    $discount = Discount::create([
        'name' => '45% Discount',
        'handle' => '45-percent-discount',
        'type' => AdvancedAmountOff::class,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDays(5),
        'priority' => 1,
        'data' => [
            'percentage' => 45,
            'fixed_value' => false,
        ],
    ]);

    expect($discount->ends_at->isPast())->toBeTrue();

    // Create product with 45% discount (should reactivate the ended discount)
    $article = ErpSyncTemp::create([
        'erp_id' => 'ERP-DISC-8',
        'name' => 'Product H',
        'sku' => 'DISC-008',
        'price' => 1000,
        'discount' => 45,
        'stock' => 5,
        'provider_data' => ['article_kind' => 0],
        'attributes' => [],
    ]);

    (new CreateProductsAndVariantsJob($article, Collection::make()))->handle();

    // Discount should be reactivated (ends_at set to null)
    $discount->refresh();
    expect($discount->ends_at)->toBeNull();

    // Product should be attached to the reactivated discount
    $variant = ProductVariant::where('sku', 'DISC-008')->first();
    $product = $variant->product;

    $discountable = Discountable::where('discountable_type', Product::morphName())
        ->where('discountable_id', $product->id)
        ->where('discount_id', $discount->id)
        ->first();

    expect($discountable)->not->toBeNull();

    // Should not have created a new discount
    $discountCount = Discount::where('data->percentage', 45)->count();
    expect($discountCount)->toBe(1);
});
