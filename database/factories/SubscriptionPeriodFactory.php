<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPeriod>
 */
class SubscriptionPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'plan_id' => null,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => null,
            'plan_name_snapshot' => 'Starter',
            'base_price_cents_snapshot' => 2_900,
            'included_units_snapshot' => 1_000,
            'overage_rate_cents_snapshot' => 5,
            'billing_cycle_snapshot' => 'monthly',
            'change_reason' => 'initial',
        ];
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'subscription_id' => $subscription->id,
            'merchant_id' => $subscription->merchant_id,
            'customer_id' => $subscription->customer_id,
        ]);
    }
}
