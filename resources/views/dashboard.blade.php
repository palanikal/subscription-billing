<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Intelligence</title>
    <style>
        :root { --ink: #17212f; --muted: #64748b; --line: #e5eaf1; --surface: #ffffff; --ground: #f5f7fb; --navy: #111d35; --blue: #315efb; --sky: #e9efff; --green: #159a70; --amber: #e4942c; --red: #d95858; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--ground); color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .app { min-height: 100vh; display: grid; grid-template-columns: 252px 1fr; }
        .sidebar { background: var(--navy); color: #d6e0f4; padding: 28px 18px; display: flex; flex-direction: column; gap: 38px; }
        .brand { display: flex; align-items: center; gap: 11px; padding: 0 10px; color: #fff; font-weight: 750; letter-spacing: -.03em; }
        .brand-mark { width: 32px; height: 32px; display: grid; place-items: center; background: linear-gradient(135deg, #6d8dff, #315efb); border-radius: 10px; box-shadow: 0 7px 18px rgba(49, 94, 251, .35); font-size: 17px; }
        .brand small { display: block; color: #8290a9; font-size: 10px; font-weight: 650; letter-spacing: .1em; text-transform: uppercase; margin-top: 3px; }
        .nav-label { color: #71809a; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; margin: 0 10px 9px; }
        .nav a { text-decoration: none; color: #aab7cd; display: flex; align-items: center; gap: 12px; border-radius: 9px; padding: 11px 12px; margin: 3px 0; font-size: 14px; font-weight: 600; }
        .nav a.active { background: #223253; color: #fff; }
        .nav-icon { width: 17px; text-align: center; color: #8fa8ff; }
        .sidebar-foot { margin-top: auto; padding: 16px; border: 1px solid #2b3d60; border-radius: 13px; background: rgba(255,255,255,.03); }
        .sidebar-foot strong { color: #fff; display: block; font-size: 13px; }
        .sidebar-foot p { margin: 6px 0 0; color: #8fa0bb; font-size: 12px; line-height: 1.5; }
        main { min-width: 0; }
        .topbar { height: 76px; background: rgba(255,255,255,.86); border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 0 42px; }
        .crumb { color: var(--muted); font-size: 13px; }
        .crumb strong { color: var(--ink); }
        .status { display: flex; align-items: center; gap: 9px; color: #45606a; font-size: 12px; font-weight: 700; }
        .status-dot { height: 8px; width: 8px; border-radius: 50%; background: #35bf89; box-shadow: 0 0 0 4px #dff8ed; }
        .content { max-width: 1420px; margin: 0 auto; padding: 42px; }
        .page-heading { display: flex; justify-content: space-between; gap: 24px; align-items: flex-end; margin-bottom: 28px; }
        h1 { margin: 0; font-size: clamp(27px, 3vw, 37px); line-height: 1.1; letter-spacing: -.045em; }
        .subtitle { margin: 9px 0 0; color: var(--muted); font-size: 14px; }
        .period { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; color: #516175; font-size: 12px; white-space: nowrap; }
        .period b { color: var(--ink); }
        .metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; min-height: 144px; position: relative; overflow: hidden; }
        .metric:after { content: ""; position: absolute; width: 95px; height: 95px; border-radius: 50%; right: -42px; bottom: -51px; background: var(--tint, #eef2ff); }
        .metric-label { color: var(--muted); font-size: 12px; font-weight: 700; }
        .metric-value { color: var(--ink); font-size: 27px; font-weight: 790; letter-spacing: -.04em; margin-top: 17px; }
        .metric-note { color: #718096; font-size: 11px; margin-top: 7px; }
        .metric-note.up { color: var(--green); font-weight: 700; }
        .grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(310px, .8fr); gap: 24px; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 22px 23px 17px; border-bottom: 1px solid var(--line); }
        .card h2 { margin: 0; font-size: 16px; letter-spacing: -.02em; }
        .card-head p { color: var(--muted); font-size: 12px; margin: 5px 0 0; }
        .text-link { color: var(--blue); font-size: 12px; font-weight: 750; text-decoration: none; white-space: nowrap; }
        .customers { padding: 5px 23px 18px; }
        .customer { display: grid; grid-template-columns: minmax(110px, 1fr) minmax(165px, 1.45fr) 80px; align-items: center; gap: 17px; padding: 15px 0; border-bottom: 1px solid #eef1f5; }
        .customer:last-child { border-bottom: 0; }
        .customer-name { font-size: 13px; font-weight: 750; }
        .customer-id { color: #8a98aa; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; margin-top: 4px; }
        .meter { height: 8px; border-radius: 99px; background: #eef2f7; overflow: hidden; }
        .meter > span { display: block; height: 100%; min-width: 8px; background: linear-gradient(90deg, #5b7bff, #315efb); border-radius: inherit; }
        .usage { text-align: right; font-size: 13px; font-weight: 780; }
        .usage small { display: block; color: #8a98aa; font-size: 10px; font-weight: 600; margin-top: 3px; }
        .side-stack { display: grid; gap: 24px; }
        .runbook { padding: 21px 22px; }
        .runbook h2 { margin: 0 0 5px; font-size: 16px; }
        .runbook > p { margin: 0 0 18px; color: var(--muted); font-size: 12px; }
        .runbook-item { display: flex; align-items: flex-start; gap: 11px; padding: 13px 0; border-top: 1px solid #eef1f5; }
        .runbook-icon { flex: 0 0 27px; height: 27px; border-radius: 8px; display: grid; place-items: center; background: #edf3ff; color: #315efb; font-size: 13px; }
        .runbook-item strong { display: block; font-size: 12px; }
        .runbook-item span { display: block; color: var(--muted); font-size: 11px; line-height: 1.45; margin-top: 3px; }
        .api-box { background: #eff4ff; border: 1px solid #dbe5ff; border-radius: 14px; padding: 19px 20px; }
        .api-box h2 { font-size: 15px; margin: 0 0 7px; }
        .api-box p { color: #60708a; font-size: 12px; line-height: 1.5; margin: 0 0 14px; }
        code { display: block; overflow: auto; border-radius: 8px; padding: 10px 11px; background: #17213a; color: #dce7ff; font-size: 10px; white-space: nowrap; }
        .risk { margin-top: 24px; }
        .risk-body { padding: 4px 23px 16px; }
        .risk-row { display: grid; grid-template-columns: 1.35fr 1fr .8fr; gap: 15px; align-items: center; padding: 13px 0; border-bottom: 1px solid #eef1f5; }
        .risk-row:last-child { border-bottom: 0; }
        .risk-customer { font-size: 13px; font-weight: 700; }
        .risk-usage { color: #617083; font-size: 12px; }
        .risk-drop { color: var(--red); background: #fff0f0; border-radius: 20px; padding: 5px 8px; text-align: center; font-size: 11px; font-weight: 800; }
        .empty { max-width: 620px; margin: 12vh auto; text-align: center; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 45px; }
        .empty h1 { margin-bottom: 12px; }
        .empty p { color: var(--muted); line-height: 1.6; }
        @media (max-width: 1040px) { .app { grid-template-columns: 76px 1fr; } .brand span, .brand small, .nav-label, .nav a span:not(.nav-icon), .sidebar-foot { display: none; } .sidebar { align-items: center; padding-inline: 11px; } .brand { padding: 0; } .nav a { padding: 12px; } .grid { grid-template-columns: 1fr; } }
        @media (max-width: 760px) { .app { display: block; } .sidebar { display: none; } .topbar { height: auto; padding: 17px 20px; } .content { padding: 26px 20px; } .page-heading { align-items: flex-start; flex-direction: column; } .metrics { grid-template-columns: repeat(2, 1fr); } .customer { grid-template-columns: 1fr 84px; } .meter { display: none; } }
        @media (max-width: 440px) { .metrics { grid-template-columns: 1fr; } .topbar .status { display: none; } .risk-row { grid-template-columns: 1fr auto; } .risk-usage { display: none; } }
    </style>
</head>
<body>
@if ($merchant === null || $dashboard === null)
    <main class="empty">
        <h1>Your billing workspace is ready.</h1>
        <p>Run the demo database seeder, then refresh this page to load the subscription metrics.</p>
    </main>
@else
    @php
        $topCustomers = $dashboard['top_customers'];
        $maxUsage = max([1, ...array_column($topCustomers, 'usage_units')]);
        $topCustomer = $topCustomers[0] ?? null;
        $overage = $dashboard['projected_overage'];
        $risks = $dashboard['churn_risk_customers'];
    @endphp
    <div class="app">
        <aside class="sidebar">
            <div class="brand"><div class="brand-mark">S</div><span>Subscript<small>Billing workspace</small></span></div>
            <nav class="nav" aria-label="Main navigation">
                <p class="nav-label">Workspace</p>
                <a class="active" href="{{ route('dashboard') }}"><span class="nav-icon">▦</span><span>Overview</span></a>
                <a href="#api"><span class="nav-icon">⌁</span><span>Usage API</span></a>
                <a href="#customers"><span class="nav-icon">◫</span><span>Customers</span></a>
                <a href="#billing"><span class="nav-icon">◷</span><span>Billing cycle</span></a>
                <p class="nav-label" style="margin-top: 25px">System</p>
                <a href="#"><span class="nav-icon">⚙</span><span>Settings</span></a>
            </nav>
            <div class="sidebar-foot"><strong>Local demo mode</strong><p>Data is seeded locally and safe to reset during development.</p></div>
        </aside>

        <main>
            <header class="topbar">
                <div class="crumb"><strong>Workspace</strong> <span> / Overview</span></div>
                <div class="status"><span class="status-dot"></span>Billing data is up to date</div>
            </header>

            <section class="content">
                <div class="page-heading">
                    <div><h1>Subscription intelligence</h1><p class="subtitle">A live view of billing health for <strong>{{ $merchant->name }}</strong>.</p></div>
                    <div class="period">Usage window <b>{{ $dashboard['period']['month_start'] }}</b></div>
                </div>

                <section class="metrics" aria-label="Billing summary">
                    <article class="metric" style="--tint: #e9efff"><div class="metric-label">Projected overage</div><div class="metric-value">${{ number_format($overage['revenue_cents'] / 100, 2) }}</div><div class="metric-note up">Based on current usage</div></article>
                    <article class="metric" style="--tint: #e8faf4"><div class="metric-label">Subscriptions exceeding plan</div><div class="metric-value">{{ $overage['subscriptions_with_projected_overage'] }}</div><div class="metric-note">Ready for invoice calculation</div></article>
                    <article class="metric" style="--tint: #fff5e8"><div class="metric-label">Customers at risk</div><div class="metric-value">{{ count($risks) }}</div><div class="metric-note">Usage drop of 50% or more</div></article>
                    <article class="metric" style="--tint: #f3edff"><div class="metric-label">Leading customer usage</div><div class="metric-value">{{ number_format($topCustomer['usage_units'] ?? 0) }}</div><div class="metric-note">{{ $topCustomer['name'] ?? 'No usage data' }}</div></article>
                </section>

                <div class="grid">
                    <section class="card" id="customers">
                        <div class="card-head"><div><h2>Top customers</h2><p>Current-month metered usage, ranked highest first.</p></div><a class="text-link" href="#api">Usage API →</a></div>
                        <div class="customers">
                            @forelse ($topCustomers as $customer)
                                <div class="customer">
                                    <div><div class="customer-name">{{ $customer['name'] ?? 'Unnamed customer' }}</div><div class="customer-id">{{ $customer['external_id'] }}</div></div>
                                    <div class="meter" aria-label="{{ $customer['usage_units'] }} usage units"><span style="width: {{ min(100, ($customer['usage_units'] / $maxUsage) * 100) }}%"></span></div>
                                    <div class="usage">{{ number_format($customer['usage_units']) }}<small>units</small></div>
                                </div>
                            @empty
                                <p class="subtitle">No usage has been recorded for this billing period yet.</p>
                            @endforelse
                        </div>
                    </section>

                    <div class="side-stack">
                        <section class="card runbook" id="billing"><h2>Billing runbook</h2><p>Operational checks for this merchant.</p>
                            <div class="runbook-item"><div class="runbook-icon">✓</div><div><strong>Usage ingestion protected</strong><span>Idempotency keys prevent duplicate events.</span></div></div>
                            <div class="runbook-item"><div class="runbook-icon">◷</div><div><strong>Invoice cycle scheduled</strong><span>Due subscriptions are checked every day.</span></div></div>
                            <div class="runbook-item"><div class="runbook-icon">↗</div><div><strong>Dashboard updated</strong><span>Calculated as of {{ $dashboard['period']['as_of'] }} UTC.</span></div></div>
                        </section>
                        <section class="api-box" id="api"><h2>API quick start</h2><p>Use your merchant API key with every request. The API is ready for a frontend or external service.</p><code>POST /api/v1/usage</code></section>
                    </div>
                </div>

                <section class="card risk">
                    <div class="card-head"><div><h2>Churn-risk watchlist</h2><p>Customers whose usage dropped sharply compared with the preceding period.</p></div><span class="text-link">{{ count($risks) }} flagged</span></div>
                    <div class="risk-body">
                        @forelse ($risks as $risk)
                            <div class="risk-row"><div class="risk-customer">{{ $risk['name'] ?? $risk['external_id'] }}</div><div class="risk-usage">{{ number_format($risk['previous_usage_units']) }} → {{ number_format($risk['current_usage_units']) }} units</div><div class="risk-drop">↓ {{ $risk['usage_drop_percent'] }}%</div></div>
                        @empty
                            <p class="subtitle">No customers currently meet the churn-risk threshold.</p>
                        @endforelse
                    </div>
                </section>
            </section>
        </main>
    </div>
@endif
</body>
</html>
