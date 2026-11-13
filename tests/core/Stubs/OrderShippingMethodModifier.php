<?php

namespace Lunar\Tests\Core\Stubs;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\TaxClass;

/**
 * Shipping options for order shipping-method tests.
 *
 * Registered as a modifier because the save clears the manifest before getOptions().
 */
class OrderShippingMethodModifier extends ShippingModifier
{
    /**
     * @var list<array{identifier: string, name: string, price: int, collect: bool, option: ?string, when_postcode: ?string}>
     */
    public static array $options = [];

    /**
     * Prices for a non-collection option, keyed by the cart shipping postcode.
     *
     * @var array<string, int>
     */
    public static array $pricesByPostcode = [];

    /**
     * Non-collection price by the minimum cart line subtotal, in minor units.
     *
     * The highest matching minimum is used. An empty list keeps the option price.
     *
     * @var array<int, int>
     */
    public static array $priceByMinimumSubtotal = [];

    public static bool $requireKnownPostcode = false;

    public static function reset(): void
    {
        self::$options = [];
        self::$pricesByPostcode = [];
        self::$priceByMinimumSubtotal = [];
        self::$requireKnownPostcode = false;
    }

    public function handle(CartContract $cart, Closure $next): mixed
    {
        $taxClass = TaxClass::getDefault();

        if (! $taxClass || ! $cart->currency) {
            return $next($cart);
        }

        $postcode = (string) ($cart->shippingAddress?->postcode ?? '');
        $subtotal = (int) $cart->lines->sum(fn ($line): int => (int) ($line->subTotal?->value ?? 0));

        foreach (self::$options as $option) {
            if (filled($option['when_postcode'] ?? null) && $postcode !== $option['when_postcode']) {
                continue;
            }

            $price = $option['price'];

            if (! $option['collect'] && self::$pricesByPostcode !== []) {
                if (! array_key_exists($postcode, self::$pricesByPostcode)) {
                    if (self::$requireKnownPostcode) {
                        continue;
                    }
                } else {
                    $price = self::$pricesByPostcode[$postcode];
                }
            }

            if (! $option['collect'] && self::$priceByMinimumSubtotal !== []) {
                $tier = $this->priceForSubtotal($subtotal);

                if ($tier === null) {
                    continue;
                }

                $price = $tier;
            }

            ShippingManifest::addOption(new ShippingOption(
                name: $option['name'],
                description: $option['name'],
                identifier: $option['identifier'],
                price: new Price($price, $cart->currency, 1),
                taxClass: $taxClass,
                option: $option['option'],
                collect: $option['collect'],
            ));
        }

        return $next($cart);
    }

    private function priceForSubtotal(int $subtotal): ?int
    {
        $tier = null;
        $matchedMinimum = null;

        foreach (self::$priceByMinimumSubtotal as $minimum => $tierPrice) {
            if ($subtotal < $minimum) {
                continue;
            }

            if ($matchedMinimum === null || $minimum >= $matchedMinimum) {
                $matchedMinimum = $minimum;
                $tier = $tierPrice;
            }
        }

        return $tier;
    }
}
