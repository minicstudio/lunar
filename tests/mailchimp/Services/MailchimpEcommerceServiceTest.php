<?php

use Illuminate\Support\Facades\Config;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Requests\CreateCartRequest;
use Lunar\Mailchimp\Requests\DeleteCartRequest;
use Lunar\Mailchimp\Requests\DeleteProductRequest;
use Lunar\Mailchimp\Requests\SyncCustomerRequest;
use Lunar\Mailchimp\Requests\SyncProductRequest;
use Lunar\Mailchimp\Requests\UpdateCartRequest;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Mailchimp\Services\MailchimpService;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Tests\Core\Stubs\User;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
    $this->createChannel();

    Config::set('lunar-frontend.mailchimp.api_key', 'test-api-key');
    Config::set('lunar-frontend.mailchimp.list_id', 'test-list-id');
    Config::set('lunar-frontend.mailchimp.store_id', 'test-store-id');
    Config::set('lunar-frontend.mailchimp.server', 'us1');
    Config::set('lunar-frontend.mailchimp.sync_subscribers', true);

    $this->mailchimpService = new MailchimpService;
    $this->subscriberService = new MailchimpSubscriberService($this->mailchimpService);
    $this->ecommerceService = new MailchimpEcommerceService($this->mailchimpService, $this->subscriberService);
});

test('syncProduct syncs product with variants to Mailchimp', function () {
    $currency = Currency::where('default', true)->first();

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(['en' => 'Test Product']),
        ],
    ]);

    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEST-SKU']);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $mockResponse = MockResponse::make([
        'id' => (string) $product->id,
        'title' => 'Test Product',
        'variants' => [['id' => (string) $variant->id]],
    ], 200);

    $mockClient = new MockClient([
        SyncProductRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncProduct($product);

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', (string) $product->id);
});

test('syncProduct deletes unavailable product', function () {
    $product = Product::factory()->create(['status' => 'draft']);

    $mockResponse = MockResponse::make([], 204);

    $mockClient = new MockClient([
        DeleteProductRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncProduct($product);

    expect($result)->toBeArray()->toBeEmpty();
});

test('deleteProduct deletes product from Mailchimp', function () {
    $product = Product::factory()->create();

    $mockResponse = MockResponse::make([], 204);

    $mockClient = new MockClient([
        DeleteProductRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->deleteProduct($product);

    expect($result)->toBeTrue();
});

test('deleteProduct handles 404 gracefully', function () {
    $product = Product::factory()->create();

    $mockResponse = MockResponse::make(['error' => 'Not Found'], 404);

    $mockClient = new MockClient([
        DeleteProductRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->deleteProduct($product);

    expect($result)->toBeTrue();
});

test('syncCustomer creates customer in Mailchimp', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $mockResponse = MockResponse::make([
        'id' => md5('customer@example.com'),
        'email_address' => 'customer@example.com',
    ], 200);

    $mockClient = new MockClient([
        SyncCustomerRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncCustomer($user);

    expect($result)
        ->toBeArray()
        ->toHaveKey('email_address', 'customer@example.com');
});

test('syncCart creates cart via POST', function () {
    $currency = Currency::where('default', true)->first();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $taxClass = TaxClass::factory()->create();
    $variant->update(['tax_class_id' => $taxClass->id]);

    $cart = Cart::factory()->create(['user_id' => $user->id, 'currency_id' => $currency->id]);
    $cart->lines()->create([
        'purchasable_type' => ProductVariant::class,
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $mockResponse = MockResponse::make([
        'id' => (string) $cart->id,
        'customer' => ['email_address' => $user->email],
    ], 201);

    $mockClient = new MockClient([
        CreateCartRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncCart($cart);

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', (string) $cart->id);
});

test('syncCart falls back to PATCH when cart exists', function () {
    $currency = Currency::where('default', true)->first();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $taxClass = TaxClass::factory()->create();
    $variant->update(['tax_class_id' => $taxClass->id]);

    $cart = Cart::factory()->create(['user_id' => $user->id, 'currency_id' => $currency->id]);
    $cart->lines()->create([
        'purchasable_type' => ProductVariant::class,
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    $mockPost400 = MockResponse::make(['error' => 'Cart exists'], 400);
    $mockPatchSuccess = MockResponse::make([
        'id' => (string) $cart->id,
        'customer' => ['email_address' => $user->email],
    ], 200);

    $mockClient = new MockClient([
        CreateCartRequest::class => $mockPost400,
        UpdateCartRequest::class => $mockPatchSuccess,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncCart($cart);

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', (string) $cart->id);
});

test('syncCart throws exception when user_id is missing', function () {
    $cart = Cart::factory()->create(['user_id' => null]);

    $this->ecommerceService->syncCart($cart);
})->throws(FailedMailchimpSyncException::class, 'has no associated user');

test('deleteCart deletes cart from Mailchimp', function () {
    $mockResponse = MockResponse::make([], 204);

    $mockClient = new MockClient([
        DeleteCartRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->deleteCart('123');

    expect($result)->toBeTrue();
});

test('syncOrder creates order in Mailchimp', function () {
    $currency = Currency::where('default', true)->first();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $taxClass = TaxClass::factory()->create();
    $variant->update(['tax_class_id' => $taxClass->id]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'currency_code' => $currency->code,
    ]);

    $order->lines()->create([
        'purchasable_type' => ProductVariant::class,
        'purchasable_id' => $variant->id,
        'quantity' => 1,
        'type' => 'physical',
        'description' => 'Test Product',
        'identifier' => 'TEST-SKU',
        'unit_price' => 10000,
        'unit_quantity' => 1,
        'sub_total' => 10000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 10000,
        'tax_breakdown' => new TaxBreakdown(
            collect([
                new TaxBreakdownAmount(
                    price: new Price(0, $currency, 1),
                    identifier: 'VAT',
                    description: 'VAT',
                    percentage: 0
                ),
            ])
        ),
    ]);

    $country = Country::factory()->create();

    $order->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'contact_email' => $user->email,
    ]);

    $mockClient = new MockClient([
        MockResponse::make([
            'id' => md5($user->email),
            'email_address' => $user->email,
        ], 200),
        MockResponse::make([], 200),
        MockResponse::make([
            'id' => (string) $order->id,
            'customer' => ['email_address' => $user->email],
        ], 201),
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->ecommerceService->syncOrder($order);

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', (string) $order->id);
});

test('getCustomerIdFromEmail returns consistent hash', function () {
    $email1 = $this->mailchimpService->getCustomerIdFromEmail('test@example.com');
    $email2 = $this->mailchimpService->getCustomerIdFromEmail('TEST@EXAMPLE.COM');

    expect($email1)->toBe($email2);
});
