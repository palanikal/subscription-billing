# Subscription Billing — Project Document

## 1. Project summary

Subscription Billing is a multi-tenant Laravel application for recording metered customer usage, calculating monthly subscription invoices, and exposing billing health to each merchant. It demonstrates safe billing-domain decisions: idempotent ingestion, historical price protection, background processing, tenant isolation, and automated tests.

## 2. Business objective

The application allows a merchant to:

- receive high-volume usage events for its customers;
- avoid duplicate usage when a calling system retries a request;
- bill subscriptions monthly for a base plan plus overage;
- change a customer plan during a billing cycle without changing historical pricing;
- review top usage, estimated overage revenue, and customers whose usage has dropped sharply.

## 3. Scope delivered

| Area | Delivered capability |
| --- | --- |
| Multi-tenancy | Every merchant-owned request is resolved from an API key. |
| Usage ingestion | `POST /api/v1/usage` accepts a validated usage event. |
| Idempotency | A merchant-scoped idempotency key returns the original event on a safe retry and returns `409 Conflict` if the payload changed. |
| Billing | Monthly base charge, included units, and overage are calculated in integer cents. |
| Proration | Each active pricing segment is prorated by calendar days. |
| Plan changes | Midnight-UTC plan changes close the old pricing period and create a new immutable snapshot. |
| Aggregation | Raw usage is aggregated by customer and day through retry-safe background jobs. |
| Invoices | Jobs create one draft invoice per subscription cycle, with line items and duplicate protection. |
| Dashboard | Top five customers, projected overage, and usage-drop churn risk. |
| Operations | Docker, MySQL, Redis, worker, scheduler, repeatable seed data, and automated tests. |

## 4. Technology stack

| Layer | Technology | Responsibility |
| --- | --- | --- |
| Application | Laravel 13 / PHP 8.5 | HTTP APIs, business actions, jobs, scheduling, validation, testing. |
| Database | MySQL 8.4 | Durable billing, tenancy, subscription, usage, and invoice records. |
| Cache and queue | Redis 7.4 | Plan-pricing cache, queue transport, and billing job processing. |
| Web server | Nginx | Serves the Laravel application in Docker. |
| Containers | Docker Compose | Starts all review dependencies with one command. |
| Testing | PHPUnit / Laravel test runner | Unit and feature coverage for domain rules and APIs. |

## 5. High-level architecture

```text
Client system
  │ X-API-Key + Idempotency-Key
  ▼
Laravel API ───► MySQL usage_events (append-only)
  │                    │
  │                    ▼
  │              Scheduled aggregation
  │                    │
  │                    ▼
  └────────────► daily_usage_aggregates
                       │
                       ├──► Invoice job ───► invoices + line items
                       └──► Merchant dashboard API / web dashboard

Redis supports queues and cached current plan pricing.
```

## 6. Core business rules

1. The authenticated API key determines the merchant. A caller cannot choose another merchant by sending an ID in the request body.
2. A usage event belongs to one merchant and one customer. The customer is located by `customer_external_id` within that merchant.
3. `merchant_id + idempotency_key` is unique. The same payload may be retried safely; a different payload with the same key is rejected.
4. Raw usage events are immutable. Invoice and dashboard reporting read daily aggregates instead of repeatedly scanning raw history.
5. Currency values are stored and calculated as integer cents. Floating-point money is not used.
6. A subscription period stores a copy of the plan price, included units, and overage rate at the time it becomes active. A later plan edit cannot alter old invoices.
7. Only plan changes that occur at midnight UTC and within the active billing cycle are accepted.
8. An invoice is unique for `subscription_id + cycle_start + cycle_end`; locking and this database constraint prevent duplicate invoices during retries.
9. Churn risk means the current comparable usage is **strictly more than 50% lower** than the previous comparable period.

## 7. API surface

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/usage` | API key + rate limit | Record a usage event. |
| `POST` | `/api/v1/subscriptions/{subscription}/plan-changes` | API key | Start a new pricing period for a plan change. |
| `GET` | `/api/v1/merchants/{merchant}/dashboard` | API key | Return merchant dashboard metrics. |
| `GET` | `/` | Demo web page | Show the seeded merchant dashboard. |

Usage requests need the headers below:

```http
X-API-Key: demo-acme-api-key
Idempotency-Key: a-client-generated-unique-value
Content-Type: application/json
```

Required usage payload:

```json
{
  "customer_external_id": "cust_ada",
  "quantity": 42,
  "occurred_at": "2026-09-14T08:30:00Z",
  "metadata": {"source": "client-system"}
}
```

## 8. Data design

| Group | Tables | Purpose |
| --- | --- | --- |
| Tenancy | `merchants`, `api_keys` | Merchant ownership and hashed/revocable API keys. |
| Catalogue | `plans` | Current merchant plan definitions and current pricing. |
| Customers | `customers` | Merchant-scoped external customer identities. |
| Subscription history | `subscriptions`, `subscription_periods` | Current billing cycle and immutable historical price segments. |
| Usage | `usage_events`, `daily_usage_aggregates` | Durable input events and fast reporting/billing totals. |
| Billing | `invoices`, `invoice_line_items` | Generated billing documents and their itemized calculation output. |

Important indexes include merchant-scoped API keys, merchant-scoped customer external IDs, the idempotency key, date-range aggregate queries, subscription period ranges, and unique invoice cycles.

## 9. Local startup and review

The simplest review path needs only Docker Desktop:

```bash
docker compose up --build
```

Open `http://localhost:8080`. The bootstrap service runs migrations and creates repeatable demo data. The stack includes MySQL, Redis, the web application, Nginx, a queue worker, and the scheduler.

Run the isolated test suite with:

```bash
docker compose --profile test run --rm --build test
```

Stop the local stack with:

```bash
docker compose down
```

## 10. Verification status

The project has been checked with:

- Composer configuration validation;
- PHP/Laravel test suite: **41 tests, 260 assertions**;
- Docker-oriented isolated test profile for local validation;
- repeatable demo seed data;
- source-control excludes local environment files and tooling-only configuration.

## 11. Production considerations

The supplied Compose stack is for local review. A production release should use managed MySQL and Redis, secret storage for `APP_KEY` and credentials, database backups, monitored queue workers, scheduled execution, HTTPS, rate-limit monitoring, and an alert path for failed jobs. Demo seeders must not run in production.

At a larger event volume, raw usage can be partitioned or retained by month, queue workers can be scaled horizontally, and dashboard reporting can move to a replica or analytics store. The current aggregate table keeps ordinary dashboard and invoice queries away from the raw event stream.
