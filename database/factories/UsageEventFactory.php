<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = now()->subHour();

        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'idempotency_key' => fake()->unique()->uuid(),
            'payload_hash' => hash('sha256', fake()->unique()->uuid()),
            'quantity' => fake()->numberBetween(1, 1_000),
            'occurred_at' => $occurredAt,
            'occurred_on' => $occurredAt->toDateString(),
            'metadata' => ['source' => 'factory'],
            'created_at' => $occurredAt,
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
