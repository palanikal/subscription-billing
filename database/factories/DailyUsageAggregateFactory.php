<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyUsageAggregate>
 */
class DailyUsageAggregateFactory extends Factory
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
            'usage_date' => now()->toDateString(),
            'total_units' => fake()->numberBetween(1, 10_000),
            'event_count' => fake()->numberBetween(1, 100),
            'last_aggregated_event_id' => null,
            'aggregated_at' => now(),
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
