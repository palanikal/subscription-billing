<?php

namespace Tests\Feature\Actions\Usage;

use App\Actions\Usage\AggregateUsageForDateAction;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AggregateUsageForDateActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recomputes_daily_total_when_late_event_arrives(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        UsageEvent::factory()->forCustomer($customer)->create([
            'quantity' => 10,
            'occurred_at' => '2026-09-12 08:00:00',
            'occurred_on' => '2026-09-12',
        ]);
        UsageEvent::factory()->forCustomer($customer)->create([
            'quantity' => 15,
            'occurred_at' => '2026-09-12 12:00:00',
            'occurred_on' => '2026-09-12',
        ]);
        $action = app(AggregateUsageForDateAction::class);

        $this->assertSame(1, $action->handle(CarbonImmutable::parse('2026-09-12', 'UTC')));

        $aggregate = DailyUsageAggregate::query()->firstOrFail();

        $this->assertSame(25, $aggregate->total_units);
        $this->assertSame(2, $aggregate->event_count);

        UsageEvent::factory()->forCustomer($customer)->create([
            'quantity' => 5,
            'occurred_at' => '2026-09-12 16:00:00',
            'occurred_on' => '2026-09-12',
        ]);

        $this->assertSame(1, $action->handle(CarbonImmutable::parse('2026-09-12', 'UTC')));

        $aggregate->refresh();

        $this->assertSame(30, $aggregate->total_units);
        $this->assertSame(3, $aggregate->event_count);
        $this->assertSame(1, DailyUsageAggregate::count());
    }

    public function test_aggregates_only_usage_from_requested_utc_date(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        UsageEvent::factory()->forCustomer($customer)->create([
            'quantity' => 10,
            'occurred_at' => '2026-09-11 23:00:00',
            'occurred_on' => '2026-09-11',
        ]);
        UsageEvent::factory()->forCustomer($customer)->create([
            'quantity' => 15,
            'occurred_at' => '2026-09-12 00:30:00',
            'occurred_on' => '2026-09-12',
        ]);

        app(AggregateUsageForDateAction::class)->handle(CarbonImmutable::parse('2026-09-12', 'UTC'));

        $aggregate = DailyUsageAggregate::query()->firstOrFail();

        $this->assertSame(15, $aggregate->total_units);
        $this->assertSame(2, UsageEvent::count());
    }
}
