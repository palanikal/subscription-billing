<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'type' => 'base',
            'description' => 'Monthly base price',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->addMonthNoOverflow()->startOfMonth(),
            'quantity' => 1,
            'unit_amount_cents' => 2_900,
            'amount_cents' => 2_900,
            'pricing_snapshot' => ['base_price_cents' => 2_900],
        ];
    }
}
