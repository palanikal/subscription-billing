<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'subscription_id' => Subscription::factory(),
            'cycle_start' => now()->startOfMonth(),
            'cycle_end' => now()->addMonthNoOverflow()->startOfMonth(),
            'currency' => 'USD',
            'status' => 'finalized',
            'subtotal_cents' => 2_900,
            'total_cents' => 2_900,
            'generated_at' => now(),
            'metadata' => ['calculator_version' => 1],
        ];
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $subscription->merchant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
