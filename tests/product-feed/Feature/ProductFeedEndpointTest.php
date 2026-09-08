<?php

uses(\Lunar\Tests\ProductFeed\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\StorefrontSession;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Lunar\Models\Url;
use Lunar\ProductFeed\Encoders\CsvFeedEncoder;
use Lunar\ProductFeed\Encoders\JsonFeedEncoder;
use Lunar\ProductFeed\Encoders\XmlFeedEncoder;
use Lunar\ProductFeed\ProductFeedCache;
use Lunar\ProductFeed\Services\ProductFeedService;

beforeEach(function () {
    Language::factory()->create(['default' => true, 'code' => 'en']);
    Currency::factory()->create(['default' => true, 'code' => 'EUR', 'decimal_places' => 2]);

    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    StorefrontSession::setChannel($channel);
    StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    // Keep feed price assertions stable: stored amount is already tax-inclusive.
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);
});

/**
 * @return array{product: Product, variant: ProductVariant, channel: Channel, customerGroup: CustomerGroup}
 */
function createFeedEligibleProduct(array $productOverrides = [], array $variantOverrides = []): array
{
    $channel = Channel::where('default', true)->firstOrFail();
    $customerGroup = CustomerGroup::where('default', true)->firstOrFail();
    $currency = Currency::where('default', true)->firstOrFail();
    $language = Language::where('default', true)->firstOrFail();

    $product = Product::factory()->create(array_merge([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Feed Tee'])),
            'description' => new TranslatedText(collect(['en' => '<p>Soft cotton tee</p>'])),
        ],
    ], $productOverrides));

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $product->urls()->create(
        Url::factory()->make([
            'slug' => 'feed-tee-'.$product->id,
            'default' => true,
            'language_id' => $language->id,
        ])->toArray()
    );

    $skipPrice = array_key_exists('skip_price', $variantOverrides);
    unset($variantOverrides['skip_price']);

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'sku' => 'FEED-TEE-1',
        'stock' => 10,
        'purchasable' => 'always',
        'gtin' => '0123456789012',
        'mpn' => 'MPN-FEED-1',
    ], $variantOverrides));

    if (! $skipPrice) {
        $variant->prices()->create([
            'currency_id' => $currency->id,
            'price' => 1999,
        ]);
    }

    return [
        'product' => $product->fresh(['variants', 'brand', 'defaultUrl', 'media']),
        'variant' => $variant->fresh(['prices.currency']),
        'channel' => $channel,
        'customerGroup' => $customerGroup,
    ];
}

test('published available product with priced variant appears in csv xml and json with expected content types', function () {
    createFeedEligibleProduct();

    config(['app.name' => 'Webshop Demo']);

    $csv = $this->get('/feeds/products/csv');
    $csv->assertOk();
    expect($csv->headers->get('Content-Type'))->toStartWith('text/csv');
    expect($csv->headers->get('Cache-Control'))->toContain('public')
        ->and($csv->headers->get('Cache-Control'))->toContain('max-age=3600');
    expect($csv->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="webshop-demo-product-feed-'.now()->format('Y-m-d').'.csv"');
    expect($csv->getContent())->toContain('FEED-TEE-1')
        ->and($csv->getContent())->toContain('19.99 EUR')
        ->and($csv->getContent())->toContain('https://shop.test/feed-tee-');

    $xml = $this->get('/feeds/products/xml');
    $xml->assertOk();
    expect($xml->headers->get('Content-Type'))->toStartWith('application/xml');
    expect($xml->getContent())->toContain('xmlns:g="http://base.google.com/ns/1.0"')
        ->and($xml->getContent())->toContain('<g:id>FEED-TEE-1</g:id>')
        ->and($xml->getContent())->toContain('<g:price>19.99 EUR</g:price>');

    $json = $this->getJson('/feeds/products/json');
    $json->assertOk();
    expect($json->headers->get('Content-Type'))->toStartWith('application/json');
    $json->assertJsonFragment([
        'id' => 'FEED-TEE-1',
        'price' => [
            'amount' => '19.99',
            'currency' => 'EUR',
        ],
        'availability' => 'in_stock',
        'condition' => 'new',
    ]);
    expect($json->json('0'))->not->toHaveKey('sale_price')
        ->and($json->json('0'))->not->toHaveKey('discount_name');
});

test('draft product does not appear', function () {
    createFeedEligibleProduct(['status' => 'draft']);

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('soft-deleted product does not appear', function () {
    $created = createFeedEligibleProduct();
    $created['product']->delete();

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('soft-deleted variant does not appear while sibling live variant does', function () {
    $created = createFeedEligibleProduct();
    $currency = Currency::where('default', true)->firstOrFail();

    $liveVariant = ProductVariant::factory()->for($created['product'])->create([
        'sku' => 'FEED-TEE-LIVE',
        'stock' => 3,
        'purchasable' => 'always',
    ]);
    $liveVariant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 2599,
    ]);

    $created['variant']->delete();

    $response = $this->getJson('/feeds/products/json');
    $response->assertOk();
    $response->assertJsonFragment(['id' => 'FEED-TEE-LIVE']);
    $response->assertJsonMissing(['id' => 'FEED-TEE-1']);
});

test('variant without a valid default-currency current price does not appear', function () {
    createFeedEligibleProduct([], ['sku' => 'NO-PRICE', 'skip_price' => true]);

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('fundamentally non-purchasable product does not appear', function () {
    $created = createFeedEligibleProduct();
    $created['product']->customerGroups()->sync([
        $created['customerGroup']->id => [
            'enabled' => true,
            'visible' => true,
            'purchasable' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ],
    ]);

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('fundamentally non-purchasable variant does not appear', function () {
    createFeedEligibleProduct([], [
        'sku' => 'NOT-SELLABLE',
        'purchasable' => 'out_of_stock',
    ]);

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('purchasable variant that cannot fulfill qty 1 still appears as out_of_stock', function () {
    createFeedEligibleProduct([], [
        'sku' => 'OOS-BUT-SELLABLE',
        'stock' => 0,
        'backorder' => 0,
        'purchasable' => 'in_stock',
    ]);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'OOS-BUT-SELLABLE',
            'availability' => 'out_of_stock',
        ]);
});

test('invalid format returns 404', function () {
    $this->get('/feeds/products/yaml')->assertNotFound();
});

test('price string uses lunar decimal and currency code', function () {
    $created = createFeedEligibleProduct();
    $service = app(ProductFeedService::class);

    $item = $service->mapVariant($created['product'], $created['variant']);

    expect($item['price'])->toBe('19.99 EUR')
        ->and($item)->not->toHaveKey('sale_price')
        ->and($item)->not->toHaveKey('discount_name');
});

test('optional brand gtin and mpn are included when present', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    createFeedEligibleProduct(['brand_id' => $brand->id]);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'brand' => 'Acme',
            'gtin' => '0123456789012',
            'mpn' => 'MPN-FEED-1',
        ]);
});

test('description html is stripped', function () {
    createFeedEligibleProduct();

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'description' => 'Soft cotton tee',
        ]);
});

test('mapped text fields are trimmed', function () {
    createFeedEligibleProduct([
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => '  Feed Tee  '])),
            'description' => new TranslatedText(collect(['en' => '<p> Soft cotton tee </p>'])),
        ],
    ], [
        'sku' => '  TRIM-SKU  ',
        'gtin' => ' 0123456789012 ',
        'mpn' => ' MPN-TRIM ',
    ]);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'TRIM-SKU',
            'title' => 'Feed Tee',
            'description' => 'Soft cotton tee',
            'gtin' => '0123456789012',
            'mpn' => 'MPN-TRIM',
        ]);
});

test('review score is included from approved product reviews', function () {
    $created = createFeedEligibleProduct();

    \Lunar\Review\Models\Review::query()->create([
        'order_id' => null,
        'user_id' => null,
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $created['variant']->id,
        'attribute_data' => [
            'rating' => new \Lunar\FieldTypes\Dropdown('5'),
        ],
        'approved_at' => now(),
    ]);

    \Lunar\Review\Models\Review::query()->create([
        'order_id' => null,
        'user_id' => null,
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $created['variant']->id,
        'attribute_data' => [
            'rating' => new \Lunar\FieldTypes\Dropdown('4'),
        ],
        'approved_at' => now(),
    ]);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'FEED-TEE-1',
            'review_score' => '4.5',
        ]);
});

test('review score is omitted when there are no approved reviews', function () {
    createFeedEligibleProduct();

    $response = $this->getJson('/feeds/products/json');
    $response->assertOk();
    $response->assertJsonFragment(['id' => 'FEED-TEE-1']);
    expect($response->json('0'))->not->toHaveKey('review_score');
});

test('active catalog discount emits sale_price and discount_name while price stays original', function () {
    $created = createFeedEligibleProduct();
    $currency = Currency::where('default', true)->firstOrFail();
    $startsAt = now()->subMinute();
    $endsAt = now()->addDays(7);

    $discount = Discount::factory()->create([
        'name' => 'Spring Sale',
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [$currency->code => 500],
        ],
    ]);

    $discount->channels()->attach([
        $created['channel']->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);
    $discount->customerGroups()->attach([
        $created['customerGroup']->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $effectiveDate = $startsAt->toIso8601String().'/'.$endsAt->toIso8601String();

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'FEED-TEE-1',
            'price' => [
                'amount' => '19.99',
                'currency' => 'EUR',
            ],
            'sale_price' => [
                'amount' => '14.99',
                'currency' => 'EUR',
            ],
            'discount_name' => 'Spring Sale',
            'sale_price_effective_date' => $effectiveDate,
        ]);

    $csv = $this->get('/feeds/products/csv');
    $csv->assertOk();
    expect($csv->getContent())->toContain('Spring Sale')
        ->and($csv->getContent())->toContain('14.99 EUR')
        ->and($csv->getContent())->toContain($effectiveDate);

    $xml = $this->get('/feeds/products/xml');
    $xml->assertOk();
    expect($xml->getContent())->toContain('<g:sale_price>14.99 EUR</g:sale_price>')
        ->and($xml->getContent())->toContain('<g:sale_price_effective_date>'.$effectiveDate.'</g:sale_price_effective_date>')
        ->and($xml->getContent())->not->toContain('discount_name')
        ->and($xml->getContent())->not->toContain('Spring Sale');
});

test('sale_price_effective_date uses start plus ten years when discount has no end', function () {
    $created = createFeedEligibleProduct();
    $currency = Currency::where('default', true)->firstOrFail();
    $startsAt = now()->subMinute();

    $discount = Discount::factory()->create([
        'name' => 'Open Ended Sale',
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => $startsAt,
        'ends_at' => null,
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [$currency->code => 500],
        ],
    ]);

    $discount->channels()->attach([
        $created['channel']->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);
    $discount->customerGroups()->attach([
        $created['customerGroup']->id => ['enabled' => true, 'starts_at' => now()->subMinute()],
    ]);

    $effectiveDate = $startsAt->toIso8601String().'/'.$startsAt->copy()->addYears(10)->toIso8601String();

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'FEED-TEE-1',
            'sale_price_effective_date' => $effectiveDate,
        ]);
});

test('product_type uses main collection and json collections lists attached names', function () {
    $created = createFeedEligibleProduct();

    $parent = Collection::factory()->create([
        'attribute_data' => collect([
            'name' => new Text('Clothing'),
        ]),
        'type' => 'vertical',
        'sort' => 'manual',
    ]);

    $child = $parent->children()->create([
        'collection_group_id' => $parent->collection_group_id,
        'attribute_data' => collect([
            'name' => new Text('Shoes'),
        ]),
        'type' => 'vertical',
        'sort' => 'manual',
    ]);

    $created['product']->collections()->attach($child->id);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'FEED-TEE-1',
            'product_type' => 'Clothing',
            'collections' => ['Shoes'],
        ]);

    $xml = $this->get('/feeds/products/xml');
    expect($xml->getContent())->toContain('<g:product_type>Clothing</g:product_type>')
        ->and($xml->getContent())->not->toContain('collections')
        ->and($xml->getContent())->not->toContain('Clothing > Shoes');
});

test('variant options map to google size and color attributes', function () {
    $created = createFeedEligibleProduct();

    $colorOption = ProductOption::factory()->create([
        'name' => ['en' => 'Color'],
        'label' => ['en' => 'Color'],
    ]);
    $colorValue = ProductOptionValue::factory()->create([
        'product_option_id' => $colorOption->id,
        'name' => ['en' => 'Blue'],
    ]);

    $sizeOption = ProductOption::factory()->create([
        'name' => ['en' => 'Size (UK)'],
        'label' => ['en' => 'Size (UK)'],
    ]);
    $sizeValue = ProductOptionValue::factory()->create([
        'product_option_id' => $sizeOption->id,
        'name' => ['en' => 'UK 7'],
    ]);

    $created['variant']->values()->attach([$colorValue->id, $sizeValue->id]);

    $tag = Tag::factory()->create(['value' => 'SUMMER']);
    $created['product']->tags()->attach($tag->id);

    $this->getJson('/feeds/products/json')
        ->assertOk()
        ->assertJsonFragment([
            'id' => 'FEED-TEE-1',
            'color' => 'Blue',
            'size' => 'UK 7',
            'product_options' => [
                'Color' => 'Blue',
                'Size (UK)' => 'UK 7',
            ],
            'tags' => ['SUMMER'],
        ]);

    $xml = $this->get('/feeds/products/xml');
    expect($xml->getContent())->toContain('<g:color>Blue</g:color>')
        ->and($xml->getContent())->toContain('<g:size>UK 7</g:size>')
        ->and($xml->getContent())->not->toContain('product_options')
        ->and($xml->getContent())->not->toContain('SUMMER');
});

test('cached feed body is reused until forgotten', function () {
    createFeedEligibleProduct();

    $first = $this->getJson('/feeds/products/json');
    $first->assertOk()->assertJsonFragment(['id' => 'FEED-TEE-1']);

    Product::query()->delete();
    ProductVariant::query()->delete();

    $cached = $this->getJson('/feeds/products/json');
    $cached->assertOk()->assertJsonFragment(['id' => 'FEED-TEE-1']);

    app(ProductFeedCache::class)->forgetAll();

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('cache disabled rebuilds on every request and omits cache-control', function () {
    config(['lunar.product-feed.cache.enabled' => false]);

    createFeedEligibleProduct();

    $first = $this->getJson('/feeds/products/json');
    $first->assertOk()->assertJsonFragment(['id' => 'FEED-TEE-1']);
    expect($first->headers->get('Cache-Control') ?? '')->not->toContain('max-age=');

    Product::query()->delete();
    ProductVariant::query()->delete();

    $this->getJson('/feeds/products/json')->assertOk()->assertExactJson([]);
});

test('each format uses a distinct cache key', function () {
    createFeedEligibleProduct();

    $csv = $this->get('/feeds/products/csv')->assertOk()->getContent();
    $xml = $this->get('/feeds/products/xml')->assertOk()->getContent();
    $json = $this->get('/feeds/products/json')->assertOk()->getContent();

    expect($csv)->not->toBe($xml)
        ->and($csv)->not->toBe($json)
        ->and($xml)->not->toBe($json);

    $cache = app(ProductFeedCache::class);

    expect(cache()->has($cache->cacheKey('csv')))->toBeTrue()
        ->and(cache()->has($cache->cacheKey('xml')))->toBeTrue()
        ->and(cache()->has($cache->cacheKey('json')))->toBeTrue();
});

test('lazyById chunking still returns all eligible products', function () {
    config(['lunar.product-feed.query.chunk_size' => 1]);

    createFeedEligibleProduct([], ['sku' => 'CHUNK-A']);
    createFeedEligibleProduct([], ['sku' => 'CHUNK-B']);
    createFeedEligibleProduct([], ['sku' => 'CHUNK-C']);

    $response = $this->getJson('/feeds/products/json');
    $response->assertOk();

    $ids = collect($response->json())->pluck('id')->all();

    expect($ids)->toContain('CHUNK-A', 'CHUNK-B', 'CHUNK-C');
});

test('streamed responses match buffered encode when cache is disabled', function () {
    config(['lunar.product-feed.cache.enabled' => false]);

    createFeedEligibleProduct();

    $jsonResponse = $this->get('/feeds/products/json')->assertOk();
    $json = $jsonResponse->streamedContent();
    $xml = $this->get('/feeds/products/xml')->assertOk()->streamedContent();
    $csv = $this->get('/feeds/products/csv')->assertOk()->streamedContent();

    expect(json_decode($json, true))->toBeArray()
        ->and($json)->toContain('FEED-TEE-1')
        ->and($xml)->toContain('<g:id>FEED-TEE-1</g:id>')
        ->and($csv)->toContain('FEED-TEE-1');
});

test('encode and streamEncode produce identical bytes', function () {
    $items = [
        [
            'id' => 'SKU-1',
            'title' => 'Tee',
            'description' => 'Soft cotton',
            'availability' => 'in_stock',
            'condition' => 'new',
            'price' => '19.99 EUR',
            'link' => 'https://shop.test/tee',
            'item_group_id' => '1',
            'brand' => 'Acme',
        ],
        [
            'id' => 'SKU-2',
            'title' => 'Tee Blue',
            'description' => 'Soft cotton',
            'availability' => 'out_of_stock',
            'condition' => 'new',
            'price' => '21.00 EUR',
            'sale_price' => '18.00 EUR',
            'link' => 'https://shop.test/tee-blue',
            'item_group_id' => '1',
            'color' => 'Blue',
        ],
    ];

    foreach ([new CsvFeedEncoder, new XmlFeedEncoder, new JsonFeedEncoder] as $encoder) {
        $encoded = $encoder->encode($items);

        $handle = fopen('php://temp', 'r+');
        $encoder->streamEncode($items, $handle);
        rewind($handle);
        $streamed = stream_get_contents($handle) ?: '';
        fclose($handle);

        expect($streamed)->toBe($encoded);
    }
});
