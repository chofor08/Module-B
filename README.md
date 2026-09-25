# Module B — Order & Payment Service

Module B owns the order lifecycle: creating orders, tracking payment status, and notifying customers by email when an order is paid or refunded. It depends on Module A for product/stock data and emits a signal that Module C consumes when an order is paid.

## Table of contents

- [What Module B expects from Module A (Inventory)](#what-module-b-expects-from-module-a-inventory)
- [Order status](#order-status)
- [The "order paid" signal (for Module C)](#the-order-paid-signal-for-module-c)
- [Refunds](#refunds)
- [Getting started](#getting-started)
- [Environment configuration](#environment-configuration)
- [Migrations](#migrations)

## What Module B expects from Module A (Inventory)

Module B does not own product or stock data. Before an order can be created, Module B needs Module A to provide:

- **Purchasable items (products)** — id, name, price, and current status (active/inactive).
- **Available quantity per item** — the quantity Module A is willing to sell right now, i.e. after existing reservations/holds are accounted for.
- **An availability/reservation check** — given a product id and a requested quantity, Module A must confirm whether that quantity can be reserved for the order *before* Module B marks the order as created, and reserve it (decrement available quantity) so two orders can't both claim the last unit.
- **A release mechanism** — if an order is cancelled or expires unpaid, Module A must release the reserved quantity back into available stock.

Until Module A exposes this (as an internal API, service class, or event contract), Module B cannot safely place orders — the quantity check has to happen at the inventory boundary, not be assumed by Module B.

## Order status

Each order in Module B moves through one of the following statuses:

| Status     | Meaning                                                              |
|------------|-----------------------------------------------------------------------|
| `unpaid`   | Order created, stock reserved via Module A, awaiting payment.        |
| `paid`     | Payment confirmed. Confirmation email sent. `order.paid` signal fired. |
| `refunded` | Payment reversed. Confirmation email sent. Stock release handled via Module A. |

Confirmation emails are sent to the customer on the `unpaid → paid` and `paid → refunded` transitions.

## The "order paid" signal (for Module C)

When an order transitions to `paid`, Module B emits a signal that Module C listens for. Module C should treat this as the single source of truth for "this order is now paid" — it should not infer payment status by polling Module B's tables directly.

**Payload:**

```json
{
  // "event": "order.paid",
  // "order_id": 123,
  // "paid_at": "2026-09-25T10:15:00Z",
  // "customer": {
  //   "id": 45,
  //   "email": "customer@example.com"
  // },
  // "items": [
  //   { "product_id": 7, "quantity": 2, "unit_price": 1500 }
  // ],
  // "total": 3000,
  // "currency": "XAF"
}
```

- `order_id` — Module B's order identifier, use this to correlate with future signals (e.g. `order.refunded`).
- `items` — the product ids and quantities that were paid for, so Module C doesn't need to call back into Module B for line items.
- `total` / `currency` — the amount actually paid.

The exact transport (Laravel event, queued job, webhook, etc.) is an implementation detail Module B is free to choose, but the payload shape above is the contract Module C should be able to rely on.

## Refunds

When an order is refunded, Module B:

1. Updates the order status to `refunded`.
2. Sends a refund confirmation email to the customer.
3. Notifies Module A to release the reserved quantity back to available stock.

A corresponding `order.refunded` signal (same shape as `order.paid`, with a `refunded_at` timestamp) should be emitted for Module C if it needs to react to refunds as well.

## Getting started

If you're cloning this repo for the first time:

```bash
git clone <repo-url>
cd <project-directory>

# Copy the environment file
cp .env.example .env

# Install PHP dependencies
composer install

# Generate the application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Start the local dev server
php artisan serve
```

## Environment configuration

- Copy `.env.example` to `.env` before running anything — the app will not boot without it.
- Set your database credentials (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) in `.env` to match your local setup.
- Set mail credentials (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`) so payment/refund confirmation emails can actually send — without this, orders will still transition status, but email dispatch will fail or silently no-op depending on your mailer driver.
- Run `php artisan key:generate` after copying `.env.example` — this sets `APP_KEY`, which Laravel requires for encryption. Skipping this step throws a `MissingAppKeyException`.

## Migrations

Run `php artisan migrate` after setup to create the orders and related tables. If you pull changes that include new migrations later, re-run `php artisan migrate` to bring your local schema up to date. Use `php artisan migrate:fresh` if you need to drop all tables and start clean (development only — this destroys data).
