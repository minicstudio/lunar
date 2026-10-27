<?php

namespace Lunar\Admin\Support\Orders;

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
use Throwable;

class OrderProductLineEditFailure
{
    public static function notificationTitle(Throwable $exception): ?string
    {
        if ($exception instanceof LastOrderProductLineException) {
            return __('lunarpanel::order.action.product_line_edit.last_line');
        }

        if ($exception instanceof UnauthorizedOrderProductLineEditException) {
            return __('lunarpanel::order.action.product_line_edit.unauthorized');
        }

        if ($exception instanceof MissingOrderCartException) {
            return __('lunarpanel::order.action.product_line_edit.cart');
        }

        if ($exception instanceof OrderProductLineNotFoundException) {
            return __('lunarpanel::order.action.product_line_edit.line');
        }

        if ($exception instanceof OrderProductLinePurchasableMissingException) {
            return __('lunarpanel::order.action.product_line_edit.purchasable');
        }

        if ($exception instanceof UnpricedOrderShippingException) {
            return __('lunarpanel::order.action.product_line_edit.shipping');
        }

        if ($exception instanceof LockedOrderProductLineEditException) {
            $gates = collect($exception->reasons)
                ->map(fn (string $gate): string => __('lunarpanel::order.infolist.alert.product_line_gates.'.$gate))
                ->implode(' ');

            return $gates !== '' ? $gates : __('lunarpanel::order.action.product_line_edit.locked');
        }

        if ($exception instanceof CartException) {
            return $exception->getMessage();
        }

        if ($exception instanceof NonPurchasableItemException) {
            return __('lunarpanel::order.action.product_line_edit.not_purchasable');
        }

        if ($exception instanceof OutOfStockException) {
            return __('lunarpanel::order.action.product_line_edit.out_of_stock');
        }

        if ($exception instanceof InsufficientStockException) {
            return __('lunarpanel::order.action.product_line_edit.insufficient_stock');
        }

        if ($exception instanceof MissingCurrencyPriceException) {
            return __('lunarpanel::order.action.product_line_edit.missing_price');
        }

        return null;
    }
}
