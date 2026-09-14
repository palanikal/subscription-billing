<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\CalculateInvoiceAction;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_charges_the_full_base_price_when_usage_is_within_the_included_allowance(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod();
        $this->addUsage($customer, '2026-09-10', 100);

        $invoice = $this->calculate($subscription);

        $this->assertSame(3_000, $invoice['subtotal_cents']);
        $this->assertCount(1, $invoice['line_items']);
        $this->assertSame('base', $invoice['line_items'][0]['type']);
        $this->assertSame(3_000, $invoice['line_items'][0]['amount_cents']);
    }

    public function test_it_adds_an_overage_line_for_usage_above_the_included_allowance(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod();
        $this->addUsage($customer, '2026-09-10', 101);

        $invoice = $this->calculate($subscription);

        $this->assertSame(3_005, $invoice['total_cents']);
        $this->assertCount(2, $invoice['line_items']);
        $this->assertSame('overage', $invoice['line_items'][1]['type']);
        $this->assertSame(1, $invoice['line_items'][1]['quantity']);
        $this->assertSame(5, $invoice['line_items'][1]['amount_cents']);
    }

    public function test_it_prorates_a_subscription_that_starts_mid_cycle(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod([
            'starts_at' => '2026-09-16 00:00:00',
        ]);
        $this->addUsage($customer, '2026-09-20', 51);

        $invoice = $this->calculate($subscription);

        $this->assertSame(1_505, $invoice['total_cents']);
        $this->assertSame(1_500, $invoice['line_items'][0]['amount_cents']);
        $this->assertSame(1, $invoice['line_items'][1]['quantity']);
    }

    public function test_it_prices_usage_with_the_snapshot_for_each_period_after_a_mid_cycle_plan_change(): void
    {
        [$subscription, $customer] = $this->subscriptionWithPeriod([
            'ends_at' => '2026-09-16 00:00:00',
        ]);
        SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => null,
            'plan_name_snapshot' => 'Growth',
            'base_price_cents_snapshot' => 6_000,
            'included_units_snapshot' => 200,
            'overage_rate_cents_snapshot' => 3,
        ]);
        $this->addUsage($customer, '2026-09-10', 60);
        $this->addUsage($customer, '2026-09-20', 105);

        $invoice = $this->calculate($subscription);

        $this->assertSame(4_565, $invoice['total_cents']);
        $this->assertCount(4, $invoice['line_items']);
        $this->assertSame(50, $invoice['line_items'][1]['amount_cents']);
        $this->assertSame(15, $invoice['line_items'][3]['amount_cents']);
    }

    /**
     * @param  array<string, mixed>  $periodAttributes
     * @return array{Subscription, Customer}
     */
    private function subscriptionWithPeriod(array $periodAttributes = []): array
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
            ...$periodAttributes,
        ]);

        return [$subscription, $customer];
    }

    private function addUsage(Customer $customer, string $usageDate, int $totalUnits): void
    {
        DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => $usageDate,
            'total_units' => $totalUnits,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(Subscription $subscription): array
    {
        return app(CalculateInvoiceAction::class)->handle(
            $subscription,
            CarbonImmutable::parse('2026-09-01 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'),
        );
    }
}
