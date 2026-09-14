<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\GenerateInvoiceAction;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_draft_invoice_and_its_calculated_lines(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod();
        $this->addUsage($customer, 101);

        $invoice = $this->generate($subscription);

        $this->assertSame('draft', $invoice->status);
        $this->assertSame(3_005, $invoice->subtotal_cents);
        $this->assertSame(3_005, $invoice->total_cents);
        $this->assertCount(2, $invoice->lineItems);
        $this->assertSame('base', $invoice->lineItems[0]->type);
        $this->assertSame('overage', $invoice->lineItems[1]->type);
        $this->assertSame(1, $invoice->lineItems[1]->quantity);
        $this->assertSame(5, $invoice->lineItems[1]->amount_cents);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'subscription_id' => $subscription->id,
            'cycle_start' => '2026-09-01 00:00:00',
            'cycle_end' => '2026-10-01 00:00:00',
            'total_cents' => 3_005,
        ]);
        $this->assertDatabaseHas('invoice_line_items', [
            'invoice_id' => $invoice->id,
            'type' => 'overage',
            'quantity' => 1,
            'unit_amount_cents' => 5,
            'amount_cents' => 5,
        ]);
    }

    public function test_it_returns_the_existing_invoice_when_generation_is_retried_for_the_same_cycle(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod();
        $dailyUsage = $this->addUsage($customer, 101);

        $firstInvoice = $this->generate($subscription);
        $dailyUsage->update(['total_units' => 500]);

        $retriedInvoice = $this->generate($subscription);

        $this->assertSame($firstInvoice->id, $retriedInvoice->id);
        $this->assertSame(3_005, $retriedInvoice->total_cents);
        $this->assertCount(2, $retriedInvoice->lineItems);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(2, $firstInvoice->lineItems()->count());
    }

    /**
     * @return array{Subscription, Customer}
     */
    private function subscriptionWithPeriod(): array
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create([
            'started_at' => '2026-09-01 00:00:00',
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-10-01 00:00:00',
        ]);
        SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'plan_name_snapshot' => 'Starter',
            'base_price_cents_snapshot' => 3_000,
            'included_units_snapshot' => 100,
            'overage_rate_cents_snapshot' => 5,
        ]);

        return [$subscription, $customer];
    }

    private function addUsage(Customer $customer, int $totalUnits): DailyUsageAggregate
    {
        return DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => '2026-09-10',
            'total_units' => $totalUnits,
        ]);
    }

    private function generate(Subscription $subscription): Invoice
    {
        return app(GenerateInvoiceAction::class)->handle(
            $subscription,
            CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'),
        );

    }
}
