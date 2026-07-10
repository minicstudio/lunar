# Loyalty Plugin

> **The ledger is the source of truth; `balance` is a performance optimization and can always be rebuilt from transactions.**

## Overview

The `lunarphp/loyalty` package provides an event-driven loyalty score engine with:

- Ledger-based point tracking (`loyalty_transactions`)
- Cached balance on `loyalty_accounts` (maintained exclusively by `LoyaltyLedger`)
- FIFO lot tracking via `remaining_points` on earn rows
- Spend at order create, earn on order completed
- Cart redemption pipeline and validation

## Installation

The package is included in the monorepo. Register the Filament plugin in your panel config:

```php
use Lunar\Loyalty\LoyaltyPlugin;

->plugin(LoyaltyPlugin::make())
```

Publish config:

```bash
php artisan vendor:publish --tag=lunar.loyalty.config
```

## Balance reads

| Accessor / method | Source | Use case |
| --- | --- | --- |
| `$account->display_balance` | Cached `balance` column | Storefront, admin UI, customer account page |
| `$account->available_balance` | `SUM(remaining_points)` on non-expired earn lots | Checkout authorization, redemption validation |
| `getBalanceForDisplay()` | Same as `display_balance` | Package internals, Filament |
| `getAvailableBalance()` | Same as `available_balance` | Package internals, ledger |

**Storefront rule:** use accessors (`display_balance`, `available_balance`) in Blade/Livewire — do not call ledger methods or query `LoyaltyTransaction` directly.

In a healthy system both values match. If drift occurs, **checkout must trust the lots**, not the cache.

## Signed adjust convention

| Scenario | type | points |
| --- | --- | --- |
| Cancel spend reversal | `adjust` | positive (decrements `lifetime_spent`) |
| Refund clawback | `adjust` | negative (decrements `lifetime_earned`) |
| Staff credit | `earn` (manual) | positive |
| Staff debit | `adjust` (manual) | negative (signed) |

`earn`, `spend`, and `expire` store unsigned positive `points`. `adjust` stores **signed** `points`.

## Currency convention

All calculators use **`order_total_minor`** (integer minor currency units). Never reference specific currencies in package code.

```php
'currency' => [
    'earn_ratio' => 100,   // minor units per 1 point earned
    'redeem_ratio' => 1,   // minor units discounted per 1 point spent
    'min_redeem' => 100,
    'max_redeem_percent' => 50,
],
```

## Refund rounding

Proportional clawback of earned points on refund (always floor, customer-favourable):

```
pointsToReverse = floor((refundAmountMinor / orderTotalMinor) * earnedPoints)
```

Written as `adjust` with negative signed `points` and `event_key = order:{id}:refund:{n}`.

## Commands

| Command | Purpose |
| --- | --- |
| `loyalty:expire-points` | Expire past-due earn lots |
| `loyalty:notify-expiring-points` | Send expiration window notifications |
| `loyalty:award-birthday-points` | Award scheduled birthday points |
| `loyalty:recalculate-balances` | Audit cache vs ledger vs lots; `--fix` rebuilds cache from ledger |

## Repair command

```bash
php artisan loyalty:recalculate-balances
php artisan loyalty:recalculate-balances --account=1
php artisan loyalty:recalculate-balances --fix
```

Reports:
- **cache vs ledger** — aggregation correctness
- **cache vs lots** — authorization safety

Lot drift is reported but not auto-fixed.

## Storefront API (`lunar-frontend`)

Public integration surface for hosts. Do not import `OrderTotalCalculator`, `LoyaltyLedger`, or query loyalty tables from the storefront.

### Account balances

```php
$account = $customer->loyaltyAccount;

$account->display_balance;   // cached balance for UI
$account->available_balance; // spendable points (lot sum)
```

### Earn estimates (read-only, no DB writes)

```php
use Lunar\Loyalty\Facades\Loyalty;

Loyalty::estimateCartPoints($cart);   // checkout: "you will earn ~X points"
Loyalty::estimateOrderPoints($order); // order confirmation emails
```

Uses the same `order_completed` calculator and multiplier as real earn; returns `0` when loyalty is disabled.

### Order ledger relations

```php
$order->loyaltyEarnTransaction?->points ?? 0;  // points earned (after completed)
$order->loyaltySpendTransaction?->points ?? 0;  // points spent at order create
```

Relations are registered on `Order` via `LoyaltyServiceProvider` and keyed by `event_key` (`order:{id}:earn`, `order:{id}:spend`).

### Cart redemption

```php
app(LoyaltyRedeemer::class)->applyToCart($cart, $points);
app(LoyaltyRedeemer::class)->clearFromCart($cart);
```

## Host (`lunar-frontend`) follow-up

1. Checkout UI — `LoyaltyRedeemer::applyToCart` / `clearFromCart`
2. Use `display_balance`, `available_balance`, `Loyalty::estimateCartPoints()`, and order relations above
3. Publish `config/lunar/loyalty.php` per store
4. Schedule expire, notify, birthday, and optional recalculate-balances commands
5. Expiration notification mailable class (set `expiration.notification_mailer`)
6. Ensure `completed` and `canceled` statuses exist in published `lunar/orders.php`

## Sole balance writer

All balance mutations go through `LoyaltyLedger`. Never update `loyalty_accounts.balance` directly.
