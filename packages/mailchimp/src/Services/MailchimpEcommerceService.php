<?php

namespace Lunar\Mailchimp\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Lunar\Exceptions\SilentException;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Requests\CreateCartRequest;
use Lunar\Mailchimp\Requests\CreateOrderRequest;
use Lunar\Mailchimp\Requests\DeleteCartRequest;
use Lunar\Mailchimp\Requests\DeleteProductRequest;
use Lunar\Mailchimp\Requests\SyncCustomerRequest;
use Lunar\Mailchimp\Requests\SyncProductRequest;
use Lunar\Mailchimp\Requests\UpdateCartRequest;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;

class MailchimpEcommerceService
{
    public function __construct(
        protected MailchimpService $mailchimp,
        protected MailchimpSubscriberService $subscriberService,
    ) {}

    /**
     * Sync a product to Mailchimp Ecommerce API.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncProduct(Product $product): array
    {
        $this->mailchimp->ensureStoreIdIsSet();

        $product->loadMissing(['variants', 'collections', 'brand', 'media']);

        if (! $product->isAvailable()) {
            $this->deleteProduct($product);

            return [];
        }

        $defaultCurrency = Currency::getDefault();
        $productUrl = config('app.url').'/'.$product->localeUrl()?->first()?->slug;

        if (! $productUrl) {
            report(new SilentException("Product {$product->id} has no URL and cannot be synced to Mailchimp."));
        }

        $imageUrl = $product->getMedia('images', ['primary' => true])->first()?->getUrl('large');

        $variants = $product->variants->map(function ($variant) use ($product, $productUrl, $defaultCurrency, $imageUrl) {
            $price = $variant->getCurrentPricesIncTax()
                ->filter(fn ($price) => $price->currency->code === $defaultCurrency->code)
                ->first();

            return [
                'id' => (string) $variant->id,
                'title' => $product->translateAttribute('name') ?? '',
                'url' => $productUrl ?? '',
                'sku' => $variant->sku ?? '',
                'price' => $price ? $price->value / 100 : 0,
                'inventory_quantity' => $variant->stock ?? 0,
                'image_url' => $imageUrl ?? '',
                'visibility' => $product->status === 'published' ? 'visible' : 'hidden',
            ];
        })->toArray();

        $subCollection = $product->collections->whereNotNull('parent_id')->first();

        $data = [
            'id' => (string) $product->id,
            'title' => $product->translateAttribute('name') ?? "Product {$product->id}",
            'url' => $productUrl ?? '',
            'description' => strip_tags($product->translateAttribute('description') ?? ''),
            'vendor' => $product->brand?->name ?? '',
            'image_url' => $imageUrl ?? '',
            'variants' => $variants,
            'published_at_foreign' => $product->created_at->toIso8601String(),
            'type' => $subCollection?->translateAttribute('name') ?? '',
        ];

        $request = new SyncProductRequest($this->mailchimp->getStoreId(), (string) $product->id, $data);
        $response = $this->mailchimp->getConnector()->send($request);

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to sync product: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Delete a product from Mailchimp Ecommerce API.
     *
     * @throws FailedMailchimpSyncException
     */
    public function deleteProduct(Product $product): bool
    {
        $this->mailchimp->ensureStoreIdIsSet();

        $request = new DeleteProductRequest($this->mailchimp->getStoreId(), (string) $product->id);
        $response = $this->mailchimp->getConnector()->send($request);

        if ($response->successful() || $response->status() === 404) {
            return true;
        }

        throw new FailedMailchimpSyncException("Failed to delete product: {$response->body()}");
    }

    /**
     * Sync a customer to Mailchimp Ecommerce API.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncCustomer(Authenticatable $user): array
    {
        $this->mailchimp->ensureStoreIdIsSet();

        $customerId = $this->mailchimp->getCustomerIdFromEmail($user->email);

        $data = [
            'id' => $customerId,
            'email_address' => $user->email,
            'opt_in_status' => true,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
        ];

        $request = new SyncCustomerRequest($this->mailchimp->getStoreId(), $customerId, $data);
        $response = $this->mailchimp->getConnector()->send($request);

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to sync customer: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Sync an order to Mailchimp Ecommerce API.
     * Handles customer creation, subscriber sync with merge fields, and order creation.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncOrder(Order $order): array
    {
        $this->mailchimp->ensureStoreIdIsSet();

        $customer = $this->syncCustomerAfterOrder($order);

        $lines = $order->productLines->map(function ($line) {
            $unitPrice = $line->quantity > 0 ? ($line->total->value / $line->quantity) / 100 : 0;

            return [
                'id' => (string) $line->id,
                'product_id' => (string) $line->purchasable->product->id,
                'product_variant_id' => (string) $line->purchasable->id,
                'quantity' => $line->quantity,
                'price' => $unitPrice,
            ];
        })->toArray();

        $percentage = $order->productLines->first()->purchasable?->getTaxRate();
        $orderTotal = $order->total->decimal();
        $taxTotal = $orderTotal * $percentage;

        $data = [
            'id' => (string) $order->id,
            'customer' => $customer,
            'currency_code' => $order->currency->code,
            'order_total' => $orderTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $order->shipping_total->decimal(),
            'lines' => $lines,
            'processed_at_foreign' => $order->placed_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        $request = new CreateOrderRequest($this->mailchimp->getStoreId(), $data);
        $response = $this->mailchimp->getConnector()->send($request);

        if ($response->status() === 400) {
            // 400 could mean missing products
            // Sync products first, then retry POST.
            $this->syncOrderProducts($order);

            $response = $this->mailchimp->getConnector()->send($request);
        }

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to sync order: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Sync customer data after an order is placed.
     * Ensures the customer exists in Mailchimp and syncs subscriber with order-related merge fields.
     *
     * @throws FailedMailchimpSyncException
     */
    protected function syncCustomerAfterOrder(Order $order): array
    {
        if ($order->user_id) {
            $user = $order->user;
            $email = $user->email;
            $firstName = $user->first_name ?? '';
            $lastName = $user->last_name ?? '';
        } else {
            $billingAddress = $order->billingAddress;
            $email = $billingAddress->contact_email;
            $firstName = $billingAddress->first_name ?? '';
            $lastName = $billingAddress->last_name ?? '';
        }

        $customerId = $this->mailchimp->getCustomerIdFromEmail($email);

        $this->syncCustomerByEmail($customerId, $email, $firstName, $lastName);

        if (config('lunar-frontend.mailchimp.sync_subscribers', true)) {
            $orderData = $this->calculateOrderData($order);
            $this->subscriberService->syncSubscriberByEmail($email, $firstName, $lastName, $orderData);
        }

        return [
            'id' => $customerId,
            'email_address' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    /**
     * Sync a customer to Mailchimp Ecommerce API by email.
     *
     * @throws FailedMailchimpSyncException
     */
    protected function syncCustomerByEmail(
        string $customerId,
        string $email,
        string $firstName,
        string $lastName,
    ): array {
        $this->mailchimp->ensureStoreIdIsSet();

        $data = [
            'id' => $customerId,
            'email_address' => $email,
            'opt_in_status' => true,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];

        $request = new SyncCustomerRequest($this->mailchimp->getStoreId(), $customerId, $data);
        $response = $this->mailchimp->getConnector()->send($request);

        if ($response->successful() || $response->status() === 400) {
            return $response->json();
        }

        throw new FailedMailchimpSyncException("Failed to sync customer: {$response->body()}");
    }

    /**
     * Sync a cart to Mailchimp Ecommerce API for abandoned cart tracking.
     * Tries POST to create; if the cart already exists, falls back to PATCH.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncCart(Cart $cart): array
    {
        $this->mailchimp->ensureStoreIdIsSet();

        if (! $cart->user_id) {
            throw new FailedMailchimpSyncException("Cart {$cart->id} has no associated user.");
        }

        $cart->refresh();
        $cart->recalculate();

        $user = $cart->user;

        $lines = $cart->lines->map(function ($line) {
            $unitPrice = $line->quantity > 0 ? ($line->total->value / $line->quantity) / 100 : 0;

            return [
                'id' => (string) $line->id,
                'product_id' => (string) $line->purchasable->product->id,
                'product_variant_id' => (string) $line->purchasable->id,
                'quantity' => $line->quantity,
                'price' => $unitPrice,
            ];
        })->toArray();

        $percentage = $cart->lines->first()->purchasable->getTaxRate();
        $cartTotal = $cart->total->value / 100;
        $taxTotal = $cartTotal * $percentage;

        $data = [
            'id' => (string) $cart->id,
            'customer' => [
                'id' => $this->mailchimp->getCustomerIdFromEmail($user->email),
                'email_address' => $user->email,
                'opt_in_status' => true,
            ],
            'currency_code' => $cart->currency->code,
            'order_total' => $cartTotal,
            'tax_total' => $taxTotal,
            'lines' => $lines,
            'checkout_url' => route('lfp.checkout.details'),
        ];

        $storeId = $this->mailchimp->getStoreId();
        $cartId = (string) $cart->id;

        // Try creating the cart
        $response = $this->mailchimp->getConnector()->send(
            new CreateCartRequest($storeId, $data)
        );

        if ($response->status() === 400) {
            // 400 could mean missing products or cart already exists.
            // Sync products first, then retry POST.
            $this->syncCartProducts($cart);

            $response = $this->mailchimp->getConnector()->send(
                new CreateCartRequest($storeId, $data)
            );
        }

        // If still 400, the cart already exists — update via PATCH
        if ($response->status() === 400) {
            $response = $this->mailchimp->getConnector()->send(
                new UpdateCartRequest($storeId, $cartId, $data)
            );
        }

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to sync cart: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Delete a cart from Mailchimp (when order is placed or cart is abandoned).
     *
     * @throws FailedMailchimpSyncException
     */
    public function deleteCart(string $cartId): bool
    {
        $this->mailchimp->ensureStoreIdIsSet();

        $request = new DeleteCartRequest($this->mailchimp->getStoreId(), $cartId);
        $response = $this->mailchimp->getConnector()->send($request);

        if ($response->successful() || $response->status() === 404) {
            return true;
        }

        throw new FailedMailchimpSyncException("Failed to delete cart: {$response->body()}");
    }

    /**
     * Sync all products in the cart to Mailchimp.
     */
    protected function syncCartProducts(Cart $cart): void
    {
        $products = $cart->lines
            ->pluck('purchasable.product')
            ->unique('id')
            ->filter();

        foreach ($products as $product) {
            try {
                $this->syncProduct($product);
            } catch (\Exception $e) {
                Log::warning('Failed to sync product to Mailchimp', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sync all products in the order to Mailchimp to ensure they exist before syncing the order.
     *
     * @param  Order  $order  The order whose products need to be synced.
     */
    protected function syncOrderProducts(Order $order): void
    {
        $products = $order->productLines
            ->pluck('purchasable.product')
            ->unique('id')
            ->filter();

        foreach ($products as $product) {
            try {
                $this->syncProduct($product);
            } catch (\Exception $e) {
                Log::warning('Failed to sync product to Mailchimp', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Calculate order data including contact details, address, and preferences based on order items.
     */
    public function calculateOrderData(Order $order): array
    {
        return collect([
            $this->extractCategoryPreferences($order),
            $this->extractCustomOptionPreferences($order),
            $this->extractAddressDetails($order),
        ])->collapse()->all();
    }

    /**
     * Extract preferred category and subcategory from the order's product lines for merge fields.
     *
     * @return array Associative array with preferred category and subcategory merge field tags and values.
     */
    protected function extractCategoryPreferences(Order $order): array
    {
        $collections = collect($order->productLines)
            ->pluck('purchasable.product.collections')
            ->flatten();

        $categories = $collections
            ->whereNull('parent_id')
            ->map(fn ($c) => $c?->translateAttribute('name'))
            ->filter()
            ->all();

        $subcategories = $collections
            ->whereNotNull('parent_id')
            ->map(fn ($c) => $c?->translateAttribute('name'))
            ->filter()
            ->all();

        return [
            config('lunar-frontend.mailchimp.merge_fields.preferred_category') => $this->getMostFrequent($categories),
            config('lunar-frontend.mailchimp.merge_fields.preferred_subcategory') => $this->getMostFrequent($subcategories),
        ];
    }

    /**
     * Extract custom option preferences from the order's product lines based on configured option fields.
     *
     * @return array Associative array with custom option merge field tags and the most frequently selected
     */
    protected function extractCustomOptionPreferences(Order $order): array
    {
        $optionFields = config('lunar-frontend.mailchimp.option_fields', []);

        $customOptionValues = collect($order->productLines)
            ->flatMap(fn ($line) => $line->purchasable->values)
            ->filter(fn ($value) => $value->option?->handle && $value->translate('name'))
            ->groupBy(fn ($value) => $value->option->handle)
            ->map(fn ($values) => $values->map(fn ($v) => $v->translate('name'))->all())
            ->all();

        return collect($optionFields)
            ->filter(fn ($config) => isset($config['handle'], $customOptionValues[$config['handle']]))
            ->mapWithKeys(fn ($config, $tag) => [
                $tag => $this->getMostFrequent($customOptionValues[$config['handle']]),
            ])
            ->all();
    }

    /**
     * Extract address details from the order's shipping or billing address for merge fields.
     *
     *
     * @return array Associative array with address-related merge field tags and values (phone, address).
     */
    protected function extractAddressDetails(Order $order): array
    {
        $address = $order->shippingAddress ?? $order->billingAddress;

        if (! $address) {
            return [];
        }

        return collect([
            'phone' => [
                'tag' => config('lunar-frontend.mailchimp.merge_fields.phone'),
                'value' => $address->contact_phone,
            ],
            'address' => [
                'tag' => config('lunar-frontend.mailchimp.merge_fields.address'),
                'value' => ($address->line_one || $address->city || $address->postcode) ? [
                    'addr1' => $address->line_one ?? '',
                    'addr2' => $address->line_two ?? '',
                    'city' => $address->city ?? '',
                    'state' => $address->state ?? '',
                    'zip' => $address->postcode ?? '',
                    'country' => $address->country?->iso2 ?? '',
                ] : null,
            ],
        ])
            ->filter(fn ($field) => $field['tag'] && $field['value'])
            ->mapWithKeys(fn ($field) => [$field['tag'] => $field['value']])
            ->all();
    }

    /**
     * Get the most frequently occurring value from an array of values.
     *
     * @param  array  $values  Array of values to analyze.
     * @return string The most frequently occurring value, or an empty string if the array is
     */
    protected function getMostFrequent(array $values): string
    {
        return collect($values)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? '';
    }
}
