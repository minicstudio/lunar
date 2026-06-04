# Lunar Minic

Custom fork of [Lunar PHP](https://lunarphp.com/) 1.x (`lunarphp/lunar-minic`): the ecommerce engine and Filament admin for the Minic webshop stack—catalog, pricing, carts, orders, discounts, shipping, payments, ERP, Mailchimp, and search.

**Storefront is separate.** Customer routes, Livewire checkout, and payment finalization live in [`minic/lunar-frontend`](../lunar-frontend). This repo is domain logic, integrations, migrations, and admin only.

## Before you implement

1. Read **[PROJECT_SPECIFICATION.md](docs/system/PROJECT_SPECIFICATION.md)** — architecture, domain rules, host integration, and fork-specific risks.
2. Use **[CODE_MAP.md](docs/system/CODE_MAP.md)** to find where code belongs (`packages/core`, `packages/admin`, add-ons, tests).
3. For flows that touch carts, orders, or pricing, check the **design** docs (engine behavior only; not storefront orchestration).
4. Follow **[coding standards](docs/conventions/coding-standards.md)** and **[Laravel conventions](docs/conventions/laravel-conventions.md)** when writing or changing code.
5. Follow **[AGENTS.md](AGENTS.md)** (Laravel Boost + agent rules). Activate the relevant **skill** under [`.ai/skills/`](.ai/skills/) when working in that domain.
6. Add or update tests per **[TESTING.md](docs/system/TESTING.md)**.

## Documentation

### System (`docs/system/`)

| Doc | Purpose |
| --- | --- |
| [PROJECT_SPECIFICATION.md](docs/system/PROJECT_SPECIFICATION.md) | Technical spec and source of truth for this fork |
| [CODE_MAP.md](docs/system/CODE_MAP.md) | Where to look and how packages connect |
| [TESTING.md](docs/system/TESTING.md) | What to test here vs in `lunar-frontend` |
| [CHANGELOG.md](docs/changelog/) | Notable changes |

### Design (`docs/design/`)

| Doc | Purpose |
| --- | --- |
| [checkout.md](docs/design/checkout.md) | Cart, shipping, order creation, payment infrastructure |
| [order_processing.md](docs/design/order_processing.md) | Order status, AWB, ERP, invoices |
| [pricing_and_discounts.md](docs/design/pricing_and_discounts.md) | Pricing pipelines, discounts, cart totals |

### Conventions (`docs/conventions/`)

| Doc | Purpose |
| --- | --- |
| [coding-standards.md](docs/conventions/coding-standards.md) | PHPDoc, naming, structure, and readability rules |
| [laravel-conventions.md](docs/conventions/laravel-conventions.md) | Eloquent, events, and Collection usage |

### Database

| Doc | Purpose |
| --- | --- |
| [schema.dbml](docs/database/schema.dbml) | Database schema reference |

### AI guidelines & skills (`.ai/`)

| Path | Purpose |
| --- | --- |
| [base-guidelines.md](.ai/guidelines/base-guidelines.md) | Agent workflow and documentation rules |
| [behavioral-guidelines.md](.ai/guidelines/behavioral-guidelines.md) | How agents should work in this project |
| [`.ai/skills/`](.ai/skills/) | Domain skills (activate when relevant): `magister`, `smartbill`, `mailchimp`, `pest-testing` |

### Agent entrypoints

| Path | Purpose |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Cursor / Laravel Boost rules for this repo |
| [`commands/`](commands/) | Prompt workflows (e.g. bug fix, review, commit) |

## Layout (short)

- `packages/core` — domain models, pipelines, config, migrations
- `packages/admin` — Filament panel
- Other `packages/*` — payments, shipping, ERP, search, blog, reviews, etc.
- `tests/` — Pest suites by package/domain
