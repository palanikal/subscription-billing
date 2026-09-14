<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\ChangeSubscriptionPlanAction;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChangeSubscriptionPlanActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_closes_the_old_period_and_creates_a_price_snapshot_for_the_new_plan(): void
    {
        $merchant = Merchant::factory()->create();
        $starter = Plan::factory()->for($merchant)->create([
            'name' => 'Starter',
            'base_price_cents' => 3_000,
            'included_units' => 100,
            'overage_rate_cents' => 5,
        ]);
        $growth = Plan::factory()->for($merchant)->create([
            'name' => 'Growth',
            'base_price_cents' => 6_000,
            'included_units' => 200,
            'overage_rate_cents' => 3,
        ]);
        [$subscription, $oldPeriod] = $this->subscriptionWithPeriod($merchant, $starter);

        $newPeriod = app(ChangeSubscriptionPlanAction::class)->handle(
            $merchant,
            $subscription,
            $growth->id,
            CarbonImmutable::parse('2026-09-16 00:00:00', 'UTC'),
        );
        $growth->update([
            'base_price_cents' => 9_000,
            'included_units' => 900,
            'overage_rate_cents' => 9,
        ]);

        $this->assertSame('2026-09-16 00:00:00', $oldPeriod->refresh()->ends_at?->utc()->toDateTimeString());
        $this->assertSame($growth->id, $newPeriod->plan_id);
        $this->assertSame('Growth', $newPeriod->plan_name_snapshot);
        $this->assertSame(6_000, $newPeriod->base_price_cents_snapshot);
        $this->assertSame(200, $newPeriod->included_units_snapshot);
        $this->assertSame(3, $newPeriod->overage_rate_cents_snapshot);
        $this->assertSame('upgrade', $newPeriod->change_reason);
        $this->assertSame(2, $subscription->periods()->count());
    }

    public function test_it_rejects_an_out_of_order_change_that_would_close_the_active_period_before_it_starts(): void
    {
        $merchant = Merchant::factory()->create();
        $starter = Plan::factory()->for($merchant)->create(['base_price_cents' => 3_000]);
        $growth = Plan::factory()->for($merchant)->create(['base_price_cents' => 6_000]);
        $enterprise = Plan::factory()->for($merchant)->create(['base_price_cents' => 9_000]);
        [$subscription] = $this->subscriptionWithPeriod($merchant, $starter);
        $action = app(ChangeSubscriptionPlanAction::class);

        $action->handle(
            $merchant,
            $subscription,
            $growth->id,
            CarbonImmutable::parse('2026-09-16 00:00:00', 'UTC'),
        );

        try {
            $action->handle(
                $merchant,
                $subscription,
                $enterprise->id,
                CarbonImmutable::parse('2026-09-12 00:00:00', 'UTC'),
            );
            $this->fail('An out-of-order plan change should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Plan changes must be after the active pricing period starts.'],
                $exception->errors()['effective_at'],
            );
        }

        $this->assertSame(2, $subscription->periods()->count());
    }

    /**
     * @return array{Subscription, SubscriptionPeriod}
     */
    private function subscriptionWithPeriod(Merchant $merchant, Plan $plan): array
    {
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create([
            'started_at' => '2026-09-01 00:00:00',
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-10-01 00:00:00',
        ]);
        $period = SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'plan_id' => $plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'plan_name_snapshot' => $plan->name,
            'base_price_cents_snapshot' => $plan->base_price_cents,
            'included_units_snapshot' => $plan->included_units,
            'overage_rate_cents_snapshot' => $plan->overage_rate_cents,
            'billing_cycle_snapshot' => $plan->billing_cycle,
        ]);

        return [$subscription, $period];
    }
}
