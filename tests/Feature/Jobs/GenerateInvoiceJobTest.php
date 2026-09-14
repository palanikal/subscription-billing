<?php

namespace Tests\Feature\Jobs;

use App\Actions\Billing\GenerateInvoiceAction;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finalizes_one_invoice_when_the_same_job_is_run_again(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod();
        DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => '2026-09-10',
            'total_units' => 101,
        ]);
        $job = new GenerateInvoiceJob(
            $subscription->id,
            '2026-09-01 00:00:00',
            '2026-10-01 00:00:00',
        );

        $job->handle(app(GenerateInvoiceAction::class));
        $job->handle(app(GenerateInvoiceAction::class));

        $invoice = Invoice::query()->sole();

        $this->assertSame('finalized', $invoice->status);
        $this->assertSame(3_005, $invoice->total_cents);
        $this->assertSame(2, $invoice->lineItems()->count());
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_line_items', 2);
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
}
