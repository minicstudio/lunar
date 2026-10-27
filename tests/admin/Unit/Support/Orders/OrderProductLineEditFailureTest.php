<?php

use Illuminate\Support\MessageBag;
use Lunar\Admin\Support\Orders\OrderProductLineEditFailure;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Exceptions\InsufficientStockException;
use Lunar\Exceptions\MissingCurrencyPriceException;
use Lunar\Exceptions\NonPurchasableItemException;
use Lunar\Exceptions\Orders\LastOrderProductLineException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\OrderProductLineNotFoundException;
use Lunar\Exceptions\Orders\OrderProductLinePurchasableMissingException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Exceptions\Orders\UnpricedOrderShippingException;
use Lunar\Exceptions\OutOfStockException;
use Lunar\Tests\Admin\TestCase;

uses(TestCase::class);

it('maps each product line edit failure to a toast title', function (string $case) {
    [$exception, $key] = match ($case) {
        'last line' => [new LastOrderProductLineException, 'lunarpanel::order.action.product_line_edit.last_line'],
        'unauthorized' => [new UnauthorizedOrderProductLineEditException, 'lunarpanel::order.action.product_line_edit.unauthorized'],
        'missing cart' => [new MissingOrderCartException, 'lunarpanel::order.action.product_line_edit.cart'],
        'missing line' => [new OrderProductLineNotFoundException, 'lunarpanel::order.action.product_line_edit.line'],
        'missing variant' => [new OrderProductLinePurchasableMissingException, 'lunarpanel::order.action.product_line_edit.purchasable'],
        'shipping' => [new UnpricedOrderShippingException, 'lunarpanel::order.action.product_line_edit.shipping'],
        'not purchasable' => [new NonPurchasableItemException('Variant'), 'lunarpanel::order.action.product_line_edit.not_purchasable'],
        'out of stock' => [new OutOfStockException, 'lunarpanel::order.action.product_line_edit.out_of_stock'],
        'insufficient stock' => [new InsufficientStockException, 'lunarpanel::order.action.product_line_edit.insufficient_stock'],
        'missing price' => [new MissingCurrencyPriceException, 'lunarpanel::order.action.product_line_edit.missing_price'],
    };

    expect(OrderProductLineEditFailure::notificationTitle($exception))->toBe(__($key));
})->with([
    'last line',
    'unauthorized',
    'missing cart',
    'missing line',
    'missing variant',
    'shipping',
    'not purchasable',
    'out of stock',
    'insufficient stock',
    'missing price',
]);

it('names every failed gate when the order is locked', function () {
    $title = OrderProductLineEditFailure::notificationTitle(new LockedOrderProductLineEditException(['paid', 'awb']));

    expect($title)->toBe(__('lunarpanel::order.infolist.alert.product_line_gates.paid').' '.__('lunarpanel::order.infolist.alert.product_line_gates.awb'));
});

it('uses the cart validation message for a rejected quantity', function () {
    $exception = new CartException(new MessageBag([
        'cart' => 'Quantity 2 must be in increments of 4',
    ]));

    expect(OrderProductLineEditFailure::notificationTitle($exception))
        ->toBe('Quantity 2 must be in increments of 4');
});
