# Subscription Billing — Flow Document

## 1. End-to-end system flow

```mermaid
flowchart TD
    A[Client system] -->|X-API-Key + Idempotency-Key| B[Usage API]
    B --> C{Valid active merchant key?}
    C -- No --> D[401 response]
    C -- Yes --> E{Request valid and within rate limit?}
    E -- No --> F[422 or 429 response]
    E -- Yes --> G[Record raw usage event]
    G --> H[(usage_events)]
    H --> I[Hourly aggregation job]
    I --> J[(daily_usage_aggregates)]
    J --> K[Dashboard API and web dashboard]
    J --> L[Due invoice job]
    L --> M[(invoices and invoice_line_items)]
```

## 2. Usage ingestion flow

```mermaid
sequenceDiagram
    participant Client
    participant API as Laravel Usage API
    participant Auth as API-Key Middleware
    participant DB as MySQL

    Client->>API: POST /api/v1/usage
    API->>Auth: Read X-API-Key
    Auth->>DB: Find active hash-matched API key
    DB-->>Auth: Merchant context
    Auth-->>API: Authenticated merchant
    API->>API: Validate body and Idempotency-Key
    API->>DB: Find merchant + idempotency key
    alt identical previous request
        DB-->>API: Existing event
        API-->>Client: 200, duplicate = true
    else key reused with changed payload
        API-->>Client: 409 Conflict
    else new request
        API->>DB: Insert append-only usage event
        DB-->>API: Created event
        API-->>Client: 201, duplicate = false
    end
```

### Why this flow is safe

1. The merchant is not accepted from user input; it comes from the authenticated key.
2. A canonical payload hash includes the customer ID, quantity, normalized UTC timestamp, and metadata.
3. A database unique key is the final duplicate safeguard if requests arrive simultaneously.
4. Usage is durable before background aggregation starts, so an interruption does not lose events.

## 3. Daily aggregation flow

```mermaid
flowchart LR
    A[Scheduler each hour] --> B[Queue jobs for today, yesterday, two days ago]
    B --> C[Read raw events for one UTC day]
    C --> D[Group by merchant and customer]
    D --> E[Calculate total units and event count]
    E --> F[Upsert one daily aggregate per customer/day]
    F --> G[Dashboard and billing read aggregate table]
```

The job recalculates a small recent window rather than only processing “new” events. This makes the aggregate correct even when an event arrives late or a job retries.

## 4. Plan-change flow

```mermaid
sequenceDiagram
    participant Client
    participant API as Plan Change API
    participant Billing as ChangeSubscriptionPlanAction
    participant Cache as Redis cache
    participant DB as MySQL

    Client->>API: POST plan change with plan_id and effective_at
    API->>Billing: Validate active tenant-owned subscription
    Billing->>Billing: Require midnight UTC within active cycle
    Billing->>DB: Lock subscription and current pricing period
    Billing->>DB: Close old period at effective_at
    Billing->>Cache: Read current plan pricing, if not cached
    Cache-->>Billing: Current plan data
    Billing->>DB: Create new period with price snapshots
    Billing-->>Client: New subscription-period response
```

The snapshot stores plan name, base price, included units, overage rate, and billing cycle. Later edits to the current plan do not change the prior period.

## 5. Invoice generation flow

```mermaid
flowchart TD
    A[Daily 00:15 UTC scheduler] --> B[Find subscriptions with completed cycles]
    B --> C[Queue invoice generation]
    C --> D[Lock subscription in transaction]
    D --> E{Invoice for cycle already exists?}
    E -- Yes --> F[Return existing invoice]
    E -- No --> G[Load period snapshots and daily usage]
    G --> H[Calculate prorated base and overage by segment]
    H --> I[Create draft invoice]
    I --> J[Create immutable line items]
    J --> K[Commit transaction]
```

### Calculation example

For a 30-day cycle, a plan active for 10 days with a $30.00 monthly price and 300 included units contributes:

```text
base charge    = round(3000 cents × 10 / 30) = 1000 cents
included units = floor(300 × 10 / 30) = 100 units
overage        = max(0, segment usage - 100) × segment overage rate
```

Each plan segment is calculated separately. This is what makes an upgrade or downgrade in the middle of the month fair and explainable.

## 6. Dashboard flow

```mermaid
flowchart TD
    A[Merchant dashboard request] --> B[API-key tenant authentication]
    B --> C[Load month-to-date daily aggregates]
    C --> D[Rank top five customers]
    C --> E[Compare equivalent previous-period usage]
    E --> F[Flag drop greater than 50%]
    C --> G[Project pace across pricing segments]
    G --> H[Estimated overage revenue]
    D --> I[Dashboard response / Blade view]
    F --> I
    H --> I
```

The projected-overage number is an estimate based on observed month-to-date pace. It is clearly separate from a generated invoice.

## 7. Local validation flow

```mermaid
flowchart LR
    A[Install Docker Desktop] --> B[docker compose up --build]
    B --> C[Bootstrap migrates and seeds data]
    C --> D[Open http://localhost:8080]
    D --> E[Send API requests from README]
    E --> F[Run Docker test profile]
    F --> G[Review 41 billing-focused tests]
```

Recommended verification command:

```bash
docker compose --profile test run --rm --build test
```

## 8. Failure-handling summary

| Risk | Protection in the flow |
| --- | --- |
| Duplicate client retry | Merchant-scoped idempotency key and payload hash. |
| Concurrent duplicate writes | Database unique constraint and duplicate recovery. |
| Late usage event | Recent days are recalculated each hour. |
| Duplicate invoice job | Transaction, subscription lock, and unique invoice-cycle index. |
| Price edited after signup | Immutable subscription-period snapshot. |
| Cross-merchant access | API key resolves tenant before business logic. |
| Raw-event table grows large | Reporting uses daily aggregates and indexed date ranges. |
