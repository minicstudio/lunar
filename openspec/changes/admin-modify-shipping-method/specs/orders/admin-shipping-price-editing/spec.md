# Spec Delta

## MODIFIED Requirements

### Requirement: The action sits on the shipping strip

The order screen MUST show "Modify shipping price" on the shipping strip above the product list, directly under the price that strip shows, end-aligned with that price. It MUST show that action only when the viewer can manage orders, a product-line change would be accepted, and the order has exactly one shipping line. Other locales MUST use the same meaning.

#### Scenario: Editable order with one shipping line

- **WHEN** an authorized administrator opens an editable order that has one shipping line and the locale is English
- **THEN** the shipping strip offers "Modify shipping price" directly under the shipping price

#### Scenario: Locked order

- **WHEN** an authorized administrator opens an order that would reject a product-line change
- **THEN** the shipping strip hides the action and the existing gate notice still names each failed gate

#### Scenario: No shipping line

- **WHEN** an authorized administrator opens an editable order that has no shipping line
- **THEN** the screen does not offer the action and no shipping line is created

#### Scenario: Several shipping lines

- **WHEN** an authorized administrator opens an editable order that has more than one shipping line
- **THEN** the screen does not offer the action
