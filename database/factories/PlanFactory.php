<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'billing_cycle' => 'monthly',
            'base_price_cents' => fake()->numberBetween(1_000, 10_000),
            'included_units' => fake()->numberBetween(100, 10_000),
            'overage_rate_cents' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'version' => 1,
        ];
    }
}
