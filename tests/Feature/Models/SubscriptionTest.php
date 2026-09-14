<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_subscription_returns_its_open_pricing_period(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create();
        SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-09-01 00:00:00',
        ]);
        $currentPeriod = SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
        ]);

        $this->assertSame($currentPeriod->id, $subscription->currentPeriod()->firstOrFail()->id);
    }

    public function test_subscription_period_keeps_its_original_price_after_plan_changes(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $plan = Plan::factory()->for($merchant)->create([
            'base_price_cents' => 2_900,
            'included_units' => 1_000,
            'overage_rate_cents' => 5,
        ]);
        $subscription = Subscription::factory()->forCustomer($customer)->create();
        $period = SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'plan_id' => $plan->id,
            'base_price_cents_snapshot' => 2_900,
            'included_units_snapshot' => 1_000,
            'overage_rate_cents_snapshot' => 5,
        ]);

        $plan->update([
            'base_price_cents' => 5_900,
            'included_units' => 2_000,
            'overage_rate_cents' => 8,
        ]);

        $period->refresh();

        $this->assertSame(2_900, $period->base_price_cents_snapshot);
        $this->assertSame(1_000, $period->included_units_snapshot);
        $this->assertSame(5, $period->overage_rate_cents_snapshot);
    }
}
