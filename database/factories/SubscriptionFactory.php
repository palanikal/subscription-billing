<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'status' => 'active',
            'started_at' => now()->startOfMonth(),
            'ended_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->addMonthNoOverflow()->startOfMonth(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $customer->merchant_id,
            'customer_id' => $customer->id,
        ]);
    }
}
