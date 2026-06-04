# Coding Standards

Principle: Optimize for readability and maintainability over brevity.

## PHPDoc

- All methods MUST have PHPDoc.
- Include parameter descriptions when the purpose is not obvious.
- Include @return descriptions for collections, DTOs, and domain objects.
- Use generics where possible.

Example:

```php
/**
 * Calculate available shipping methods for the cart.
 *
 * @param Cart $cart The cart being evaluated.
 * @param Customer|null $customer Current customer.
 * @return Collection<int, ShippingMethod>
 */
```

## Function Responsibility

- Each method should have a single responsibility.
- Write small, focused, and easy-to-follow methods.
- Extract complex logic into dedicated methods or services.
- Prioritize readability and maintainability.
- Avoid deeply nested or overly complex code.

## Early return

- Prefer early returns to reduce nesting and improve readability.
- Handle edge cases and guard clauses at the beginning of a method.
- Avoid wrapping the main logic in unnecessary `if` blocks.

Good example:

```php
public function process(Order $order): void
{
    if ($order->isCancelled()) {
        return;
    }

    $this->generateInvoice($order);
}
```

Bad example:

```php
public function process(Order $order): void
{
    if (! $order->isCancelled()) {
        $this->generateInvoice($order);
    }
}
```


## Comments

- Do not add comments that merely repeat what the code already expresses.
- Prefer self-documenting code through clear naming and small, focused methods.
- Add comments only when explaining non-obvious business rules, architectural decisions, or implementation constraints.
- Remove outdated comments when modifying code.

Good example:

```php
// Magister does not allow modifying an invoice after synchronization.
// A new invoice must be generated instead.
$this->invoiceService->createReplacementInvoice($invoice);
```

Bad example:

```php
// Create replacement invoice
$this->invoiceService->createReplacementInvoice($invoice);
```
