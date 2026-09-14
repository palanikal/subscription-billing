<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChangeSubscriptionPlanTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_authenticated_merchant_can_change_its_subscriptions_plan(): void
    {
        $merchant = Merchant::factory()->create();
        $starter = Plan::factory()->for($merchant)->create([
            'name' => 'Starter',
            'base_price_cents' => 3_000,
        ]);
        $growth = Plan::factory()->for($merchant)->create([
            'name' => 'Growth',
            'base_price_cents' => 6_000,
            'included_units' => 200,
            'overage_rate_cents' => 3,
        ]);
        $subscription = $this->subscriptionWithPlan($merchant, $starter);
        $this->createApiKey($merchant, 'merchant-key');

        $response = $this->withHeader('X-API-Key', 'merchant-key')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/plan-changes", [
                'plan_id' => $growth->id,
                'effective_at' => '2026-09-16T00:00:00Z',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.subscription_id', $subscription->id)
            ->assertJsonPath('data.plan_id', $growth->id)
            ->assertJsonPath('data.base_price_cents', 6_000)
            ->assertJsonPath('data.change_reason', 'upgrade');
        $this->assertDatabaseHas('subscription_periods', [
            'subscription_id' => $subscription->id,
            'plan_id' => $growth->id,
            'starts_at' => '2026-09-16 00:00:00',
            'base_price_cents_snapshot' => 6_000,
        ]);
    }

    public function test_it_returns_404_when_the_subscription_belongs_to_another_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $otherMerchant = Merchant::factory()->create();
        $plan = Plan::factory()->for($merchant)->create();
        $subscription = $this->subscriptionWithPlan($otherMerchant, Plan::factory()->for($otherMerchant)->create());
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeader('X-API-Key', 'merchant-key')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/plan-changes", [
                'plan_id' => $plan->id,
                'effective_at' => '2026-09-16T00:00:00Z',
            ])
            ->assertNotFound();

        $this->assertSame(1, $subscription->periods()->count());
    }

    public function test_it_rejects_a_plan_change_that_is_not_effective_at_midnight_utc(): void
    {
        $merchant = Merchant::factory()->create();
        $starter = Plan::factory()->for($merchant)->create();
        $growth = Plan::factory()->for($merchant)->create();
        $subscription = $this->subscriptionWithPlan($merchant, $starter);
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeader('X-API-Key', 'merchant-key')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/plan-changes", [
                'plan_id' => $growth->id,
                'effective_at' => '2026-09-16T12:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['effective_at']);

        $this->assertSame(1, $subscription->periods()->count());
    }

    private function subscriptionWithPlan(Merchant $merchant, Plan $plan): Subscription
    {
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create([
            'started_at' => '2026-09-01 00:00:00',
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-10-01 00:00:00',
        ]);
        SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'plan_name_snapshot' => $plan->name,
            'base_price_cents_snapshot' => $plan->base_price_cents,
            'included_units_snapshot' => $plan->included_units,
            'overage_rate_cents_snapshot' => $plan->overage_rate_cents,
            'billing_cycle_snapshot' => $plan->billing_cycle,
        ]);

        return $subscription;
    }

    private function createApiKey(Merchant $merchant, string $plainTextKey): void
    {
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', $plainTextKey),
        ]);

    }
}
