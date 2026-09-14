<?php

namespace Tests\Feature\Actions\Dashboard;

use App\Actions\Dashboard\BuildMerchantDashboardAction;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BuildMerchantDashboardActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_top_customers_projected_overage_and_churn_risk_from_daily_aggregates(): void
    {
        $merchant = Merchant::factory()->create();
        $customerA = Customer::factory()->for($merchant)->create(['external_id' => 'customer-a']);
        $customerB = Customer::factory()->for($merchant)->create(['external_id' => 'customer-b']);
        $churnRiskCustomer = Customer::factory()->for($merchant)->create(['external_id' => 'customer-churn']);
        $this->activeSubscription($customerA, 100, 5);
        $this->activeSubscription($customerB, 100, 4);
        $this->addUsage($customerA, '2026-09-10', 150);
        $this->addUsage($customerB, '2026-09-10', 100);
        $this->addUsage($churnRiskCustomer, '2026-09-10', 90);
        $this->addUsage($customerA, '2026-08-10', 300);
        $this->addUsage($customerB, '2026-08-10', 100);
        $this->addUsage($churnRiskCustomer, '2026-08-10', 200);

        $dashboard = app(BuildMerchantDashboardAction::class)->handle(
            $merchant,
            CarbonImmutable::parse('2026-09-10 12:00:00', 'UTC'),
        );

        $this->assertSame('2026-09-01', $dashboard['period']['month_start']);
        $this->assertSame([$customerA->id, $customerB->id, $churnRiskCustomer->id], array_column($dashboard['top_customers'], 'customer_id'));
        $this->assertSame([150, 100, 90], array_column($dashboard['top_customers'], 'usage_units'));
        $this->assertSame(2_550, $dashboard['projected_overage']['revenue_cents']);
        $this->assertSame(2, $dashboard['projected_overage']['subscriptions_with_projected_overage']);
        $this->assertSame([$churnRiskCustomer->id], array_column($dashboard['churn_risk_customers'], 'customer_id'));
        $this->assertSame(55, $dashboard['churn_risk_customers'][0]['usage_drop_percent']);
    }

    private function activeSubscription(Customer $customer, int $includedUnits, int $overageRateCents): void
    {
        $subscription = Subscription::factory()->forCustomer($customer)->create([
            'current_period_start' => '2026-09-01 00:00:00',
            'current_period_end' => '2026-10-01 00:00:00',
        ]);
        SubscriptionPeriod::factory()->forSubscription($subscription)->create([
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => null,
            'included_units_snapshot' => $includedUnits,
            'overage_rate_cents_snapshot' => $overageRateCents,
        ]);
    }

    private function addUsage(Customer $customer, string $usageDate, int $totalUnits): void
    {
        DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => $usageDate,
            'total_units' => $totalUnits,
        ]);

    }
}
