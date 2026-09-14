# Subscription Billing — Design Document

## 1. Design goals

The design optimizes for correctness before visual complexity. The most important properties are tenant safety, duplicate-resistant ingestion, explainable invoice history, predictable month-end processing, and a dashboard that is useful without querying a large raw-event table.

## 2. Application design

```text
app/
├── Actions/
│   ├── Billing/       Invoice calculation, plan changes, price cache
│   ├── Dashboard/     Merchant metric construction
│   └── Usage/         Event recording and daily aggregation
├── Http/
│   ├── Controllers/   Web and API entry points
│   ├── Middleware/    Merchant API-key resolution
│   ├── Requests/      Request validation
│   └── Resources/     Stable API response shaping
├── Jobs/              Queueable aggregation and invoice generation
└── Models/            Eloquent representations of billing entities
```

Controllers stay small: they validate and authorize a request, then delegate business decisions to a named Action. This makes billing calculations and ingestion behaviour independently testable.

## 3. System components

| Component | Design responsibility |
| --- | --- |
| `ResolveMerchantApiKey` middleware | Reads `X-API-Key`, compares its SHA-256 hash with active keys, attaches the merchant to the request, and records last use. |
| `RecordUsageAction` | Resolves the merchant-owned customer, creates the canonical payload hash, and implements idempotency. |
| `AggregateDailyUsageJob` | Recomputes a merchant/customer/day total from raw events; safe to run again for late events. |
| `ChangeSubscriptionPlanAction` | Closes the active pricing segment and creates a new immutable plan snapshot. |
| `CalculateInvoiceAction` | Calculates prorated base and overage line items for every pricing segment in a cycle. |
| `GenerateInvoiceAction` | Uses a transaction, lock, and unique cycle key to persist one draft invoice safely. |
| `BuildMerchantDashboardAction` | Reads daily aggregates to calculate top users, estimated overage, and churn risk. |
| Laravel scheduler + jobs | Runs aggregation hourly and starts due invoice generation daily. |

## 4. Database relationship design

```text
Merchant
 ├── API Keys
 ├── Plans
 ├── Customers
 │    ├── Subscriptions
 │    │    ├── Subscription Periods (historical price snapshots)
 │    │    └── Invoices
 │    │         └── Invoice Line Items
 │    ├── Usage Events (raw, append-only)
 │    └── Daily Usage Aggregates
```

### Design decisions

- `customers.external_id` is unique only within one merchant. This supports external systems that reuse customer IDs across tenants.
- `usage_events` retains the original event data and identity key for auditability.
- `daily_usage_aggregates` contains one customer/day row. It is the read model for billing and dashboard reports.
- `subscription_periods` separates changing plan data from historical financial records. Each row stores a price snapshot.
- `invoice_line_items.pricing_snapshot` gives an invoice an auditable calculation context.
- Foreign keys protect referential integrity; merchant-oriented indexes support tenancy and date-range queries.

## 5. Billing calculation design

Billing is monthly and uses UTC calendar boundaries. For each subscription period that overlaps the invoice cycle:

```text
prorated base cents = round(base price cents × active days / cycle days)
included units      = floor(plan included units × active days / cycle days)
overage units       = max(0, usage in the pricing segment - included units)
overage cents       = overage units × snapshot overage rate cents
invoice total       = sum(base and overage line items)
```

The key design principle is that a plan change divides the month into pricing segments. Usage in the first segment is charged at the first segment's snapshot, never at the later plan's price.

## 6. Caching and invalidation design

Current plan pricing is cached for one hour using this tenant-scoped key:

```text
plan-pricing:merchant:{merchant_id}:plan:{plan_id}
```

When a plan is updated or deleted through Eloquent, its cached price is forgotten immediately. The next lookup retrieves the current plan. Invoices do not rely on this cache; they always use the immutable subscription-period values. A future bulk SQL plan-update process must explicitly clear affected cache keys because it bypasses model events.

## 7. API and security design

### Request protections

- API keys are stored as SHA-256 hashes, not plaintext values.
- Revoked keys cannot authenticate.
- API-key middleware defines the merchant scope before a controller handles the request.
- Usage ingestion is rate-limited to `120` requests per minute per API key.
- Requests validate quantity, timestamp, customer identifier, and optional metadata.
- An idempotency key is mandatory for usage writes.

### Idempotency behaviour

| Situation | Response |
| --- | --- |
| First valid request | Creates one usage event. |
| Retry with the same key and identical canonical payload | Returns the original event with `duplicate: true`. |
| Reuse of the same key with different content | `409 Conflict`; no new event is created. |
| Two requests race with the same key | Database unique index permits only one record; the losing request loads the winner. |

## 8. Dashboard UI design

The web dashboard is a self-contained Blade view so it can run from Docker without an extra Node build step.

### Page regions

| Region | User value |
| --- | --- |
| Left navigation | Establishes the billing workspace and primary areas. |
| Header | Shows current workspace location and billing-data status. |
| Summary cards | Highlights projected overage, subscriptions exceeding plan, at-risk customers, and leading usage. |
| Top customers | Ranks current month usage with a readable visual meter. |
| Billing runbook | Explains ingestion protection, schedule status, and dashboard calculation time. |
| API quick start | Shows the main usage endpoint at a glance. |
| Churn watchlist | Focuses attention on comparable-period drops greater than 50%. |

### Visual system

- Neutral light content area with navy navigation for billing-product clarity.
- White cards, subtle borders, and blue primary interaction accents.
- Green for healthy/current data, amber for projected overage, and red for usage-risk signals.
- Responsive behaviour: navigation compresses on medium screens; sidebar hides and content stacks on mobile.
- The interface is intentionally read-oriented: it is a product dashboard, not a full operational admin portal.

## 9. Background processing design

| Process | Frequency | Reliability approach |
| --- | --- | --- |
| Recent usage aggregation | Hourly | Recalculates today and the preceding two UTC days, covering late-arriving events. |
| Due-invoice dispatch | Daily at `00:15 UTC` | Finds completed cycles and queues invoice generation. |
| Invoice generation | Queue worker | Transaction, subscription lock, and unique invoice-cycle constraint prevent duplicates. |

The Docker stack runs a queue worker and `schedule:work` process. For a multi-instance production deployment, scheduled processes use `onOneServer` and overlap locks.

## 10. Non-goals and future enhancements

The implementation deliberately does not cover payment collection, tax, discounts, credits, refunds, multi-currency conversion, hour-level proration, or a full plan-management UI. Those features can be added around the existing immutable subscription-period and invoice model without changing past invoices.
