<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'external_id' => 'cust_'.fake()->unique()->bothify('########??'),
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'status' => 'active',
        ];
    }
}
