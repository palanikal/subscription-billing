# Subscription Billing

A Laravel 13 application for multi-tenant, usage-based subscription billing. It accepts idempotent usage events, aggregates usage daily, calculates prorated invoices, supports mid-cycle plan changes, and exposes a merchant dashboard.

## What is included

- API-key tenant authentication using the `X-API-Key` header.
- A rate-limited, idempotent `POST /api/v1/usage` endpoint.
- Append-only raw usage events and retry-safe daily aggregate jobs.
- Monthly billing with integer-cent money, included usage, and overage.
- Date-level proration for mid-cycle starts, upgrades, and downgrades.
- Immutable pricing snapshots on subscription periods and invoice line items.
- Retry-safe invoice persistence, unique per subscription and billing cycle.
- Scheduled and on-demand invoice dispatching.
- Merchant dashboard metrics: top usage, projected overage, and churn risk.
- Repeatable demo seed data and automated test coverage.

## Documentation

- [Project document](docs/PROJECT_DOCUMENT.md) — scope, architecture, setup, APIs, and delivery status.
- [Design document](docs/DESIGN_DOCUMENT.md) — application, data, billing, security, UI, and background-processing design.
- [Flow document](docs/FLOW_DOCUMENT.md) — end-to-end, usage, aggregation, plan-change, invoice, and dashboard flows.

## Fastest start: Docker

Prerequisite: Docker Desktop.

```bash
docker compose up --build
```

The application is available at `http://localhost:8080`. Docker starts MySQL, Redis, the Laravel application, an Nginx web server, a queue worker, and the scheduler. It also runs migrations and demo seeds automatically.

Stop the stack with:

```bash
docker compose down
```

To remove the local database volume as well:

```bash
docker compose down -v
```

The Docker credentials and app key are deliberately local-development values. Replace every secret with your deployment platform's secret store in production.

## Local PHP start

Prerequisites: PHP 8.5, Composer, and SQLite.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In separate terminals, run the worker and scheduler:

```bash
php artisan queue:work
php artisan schedule:work
```

The checked-in SQLite database path is `database/database.sqlite`; the default `.env.example` is configured for it.

## Verification

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

To run the isolated suite through Docker, use the dedicated test profile. It uses in-memory SQLite, array cache, and a synchronous queue; it does not alter the demo MySQL or Redis data.

```bash
docker compose --profile test run --rm test
```

## Demo data

`php artisan migrate --seed` creates the following repeatable local data:

| Item | Demo value |
| --- | --- |
| Merchant | `Palani Analytics` |
| API key | `demo-palani-api-key` |
| Plans | Starter and Growth |
| Dashboard customers | Palani, Raj, Santhosh, Mani, Sathis |
| Completed invoice-cycle demo | Sathis |

The seed is safe to run again:

```bash
php artisan db:seed
```

## API examples

All endpoints use the seeded local API key:

```bash
export BILLING_API_KEY='demo-palani-api-key'
```

### Record usage

```bash
curl --request POST http://localhost:8080/api/v1/usage \
  --header "X-API-Key: ${BILLING_API_KEY}" \
  --header 'Idempotency-Key: demo-usage-001' \
  --header 'Content-Type: application/json' \
  --data '{
    "customer_external_id": "cust_palani",
    "quantity": 42,
    "occurred_at": "2026-09-14T08:30:00Z",
    "metadata": {"source": "readme-demo"}
  }'
```

Send the exact request again to see `meta.duplicate: true`. Reusing the same idempotency key with a different quantity returns `409 Conflict`.

### Change a subscription plan

On a freshly seeded database, Palani's subscription is `1` and Growth is plan `2`.

```bash
curl --request POST http://localhost:8080/api/v1/subscriptions/1/plan-changes \
  --header "X-API-Key: ${BILLING_API_KEY}" \
  --header 'Content-Type: application/json' \
  --data '{
    "plan_id": 2,
    "effective_at": "2026-09-16T00:00:00Z"
  }'
```

Plan changes must be at midnight UTC and inside the active billing cycle. The old period is closed and the new period receives its own immutable price snapshot.

### View the merchant dashboard

On a freshly seeded database, Palani Analytics is merchant `1`.

```bash
curl http://localhost:8080/api/v1/merchants/1/dashboard \
  --header "X-API-Key: ${BILLING_API_KEY}"
```

The response contains the five highest-usage customers, an estimated overage-revenue projection, and customers with a strictly greater than 50% month-over-month usage drop.

## Demonstrate background billing immediately

The scheduler runs aggregation hourly and invoice dispatching daily at `00:15 UTC`. For a quick review, the seeded Sathis subscription has a completed cycle. Dispatch its invoice now:

```bash
php artisan billing:dispatch-due-invoices
```

With Docker, the billing queue worker is already running. Locally, process it with:

```bash
php artisan queue:work --once --queue=billing
```

The database unique key on `(subscription_id, cycle_start, cycle_end)` and the invoice generator's lock make this retry-safe: rerunning the command or job returns the same invoice rather than creating another bill.

## Architecture

```text
Merchant API key
    -> usage API -> usage_events (append-only, idempotent)
    -> AggregateDailyUsageJob -> daily_usage_aggregates
    -> GenerateInvoiceJob -> invoices + invoice_line_items

Merchant dashboard -> daily_usage_aggregates + subscription-period snapshots
```

### Important rules

- Tenant ownership always comes from the authenticated API key, not a request-supplied merchant ID.
- Money is stored as integer cents. No floating-point currency calculations are used.
- Billing cycles use calendar months in UTC.
- The billing calculator prorates each plan period separately. Usage is never charged at a later plan's rate.
- `subscription_periods` preserve historical prices. Editing a plan never changes a past invoice.
- Raw usage is durable before any asynchronous processing; aggregation recomputes a small recent window so late events are handled safely.
- Invoice writes use a transaction, a row lock, and a unique cycle constraint.

### Proration

For each pricing period inside a billing month:

```text
prorated base = round(base price cents × active days / cycle days)
included units = floor(included units × active days / cycle days)
overage units = max(0, period usage - included units)
overage cents = overage units × overage rate cents
```

The dashboard's overage value is explicitly a projection based on month-to-date pace. It is not a bill or payment authorization.

## Scale notes

The ingestion table is append-only and has a tenant-scoped idempotency unique key. Reporting and billing use `daily_usage_aggregates`, not the raw event history. Range queries use dates and supporting compound indexes. At substantially higher volumes, the next operational steps would be monthly partitioning/retention for raw usage, queue worker autoscaling, and a reporting replica or warehouse.

## Plan-pricing cache and invalidation

Plan attributes used to create a new subscription-period snapshot are cached for one hour using the configured Laravel cache store. The cache key is tenant-scoped:

```text
plan-pricing:merchant:{merchant_id}:plan:{plan_id}
```

The Docker environment uses Redis; a local environment can use Laravel's configured database or array cache store. Any Eloquent update to a plan, including a price or active-status change, deletes that plan's key immediately. Deleting a plan does the same. This means the next pricing lookup receives fresh values. Bulk SQL updates bypass Eloquent events, so a future admin bulk-update workflow must explicitly forget the affected keys. Invoice calculation deliberately reads immutable `subscription_periods` snapshots, not this cache, so an updated plan can never change a historical invoice.

## Production deployment checklist

The supplied Compose configuration is for local review only. In production, provide secrets through the host or deployment platform rather than committing a populated `.env` file.

Required production settings:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<unique generated key>
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=<managed database host>
DB_DATABASE=<database name>
DB_USERNAME=<database user>
DB_PASSWORD=<secret>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=<managed redis host>
REDIS_QUEUE_RETRY_AFTER=90
```

Release sequence:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run a durable queue worker with a timeout below `REDIS_QUEUE_RETRY_AFTER` (the supplied configuration uses 60 seconds and 90 seconds respectively). Run `php artisan schedule:run` every minute through the platform scheduler or cron, or run `php artisan schedule:work` in a supervised worker process.

After deployment, check `GET /up`, API-key authentication, queue failures, scheduler execution, database backups, and application logs. Do not run demo seeds in a production environment.

## Key endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/v1/usage` | Record one idempotent usage event. |
| `POST` | `/api/v1/subscriptions/{subscription}/plan-changes` | Change plan at midnight UTC. |
| `GET` | `/api/v1/merchants/{merchant}/dashboard` | Read tenant-scoped dashboard metrics. |

## Deliberate scope limits

This implementation focuses on the billing engine. Payment collection, tax, discounts, credits, refunds, currency conversion, a polished frontend, and hour-level proration are intentionally out of scope.
