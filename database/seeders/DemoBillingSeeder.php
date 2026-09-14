<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoBillingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now('UTC')->toImmutable();
        $monthStart = $now->startOfMonth();
        $previousMonthStart = $monthStart->subMonthNoOverflow();
        $currentUsageDate = min($now->startOfDay(), $monthStart->addDays(9));
        $previousUsageDate = $previousMonthStart->addDays($monthStart->diffInDays($currentUsageDate));
        $merchant = Merchant::query()->updateOrCreate(
            ['name' => 'Acme Analytics'],
            ['timezone' => 'UTC'],
        );

        ApiKey::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Demo API key'],
            ['key_hash' => hash('sha256', 'demo-acme-api-key'), 'revoked_at' => null],
        );

        $starter = $this->plan($merchant, 'Starter', 3_000, 100, 5);
        $growth = $this->plan($merchant, 'Growth', 6_000, 200, 3);
        $ada = $this->customer($merchant, 'cust_ada', 'Ada Lovelace', 'ada@example.test');
        $ben = $this->customer($merchant, 'cust_ben', 'Ben Bitdiddle', 'ben@example.test');
        $churnRisk = $this->customer($merchant, 'cust_churn', 'Cora Churn', 'cora@example.test');
        $billingDemo = $this->customer($merchant, 'cust_billing_demo', 'Bill Demo', 'bill@example.test');

        $this->subscription($merchant, $ada, $starter, $monthStart, $monthStart->addMonthNoOverflow());
        $this->subscription($merchant, $ben, $growth, $monthStart, $monthStart->addMonthNoOverflow());
        $this->subscription($merchant, $churnRisk, $starter, $monthStart, $monthStart->addMonthNoOverflow());
        $this->subscription($merchant, $billingDemo, $starter, $previousMonthStart, $monthStart);

        $this->dailyUsage($merchant, $ada, $currentUsageDate, 150);
        $this->dailyUsage($merchant, $ben, $currentUsageDate, 100);
        $this->dailyUsage($merchant, $churnRisk, $currentUsageDate, 90);
        $this->dailyUsage($merchant, $ada, $previousUsageDate, 300);
        $this->dailyUsage($merchant, $ben, $previousUsageDate, 100);
        $this->dailyUsage($merchant, $churnRisk, $previousUsageDate, 200);
        $this->dailyUsage($merchant, $billingDemo, $previousMonthStart->addDays(4), 120);
    }

    private function plan(Merchant $merchant, string $name, int $basePriceCents, int $includedUnits, int $overageRateCents): Plan
    {
        return Plan::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => $name],
            [
                'billing_cycle' => 'monthly',
                'base_price_cents' => $basePriceCents,
                'included_units' => $includedUnits,
                'overage_rate_cents' => $overageRateCents,
                'is_active' => true,
                'version' => 1,
            ],
        );
    }

    private function customer(Merchant $merchant, string $externalId, string $name, string $email): Customer
    {
        return Customer::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'external_id' => $externalId],
            ['name' => $name, 'email' => $email, 'status' => 'active'],
        );
    }

    private function subscription(
        Merchant $merchant,
        Customer $customer,
        Plan $plan,
        CarbonImmutable $cycleStart,
        CarbonImmutable $cycleEnd,
    ): Subscription {
        $subscription = Subscription::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'current_period_start' => $cycleStart,
                'current_period_end' => $cycleEnd,
            ],
            [
                'merchant_id' => $merchant->id,
                'status' => 'active',
                'started_at' => $cycleStart,
                'ended_at' => null,
            ],
        );

        SubscriptionPeriod::query()->updateOrCreate(
            ['subscription_id' => $subscription->id, 'starts_at' => $cycleStart],
            [
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'ends_at' => null,
                'plan_name_snapshot' => $plan->name,
                'base_price_cents_snapshot' => $plan->base_price_cents,
                'included_units_snapshot' => $plan->included_units,
                'overage_rate_cents_snapshot' => $plan->overage_rate_cents,
                'billing_cycle_snapshot' => $plan->billing_cycle,
                'change_reason' => 'initial',
            ],
        );

        return $subscription;
    }

    private function dailyUsage(Merchant $merchant, Customer $customer, CarbonImmutable $usageDate, int $totalUnits): void
    {
        $attributes = [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => $usageDate,
            'total_units' => $totalUnits,
            'event_count' => 1,
            'last_aggregated_event_id' => null,
            'aggregated_at' => now('UTC'),
        ];
        $aggregate = DailyUsageAggregate::query()
            ->whereBelongsTo($customer)
            ->where('usage_date', '>=', $usageDate->startOfDay())
            ->where('usage_date', '<', $usageDate->addDay()->startOfDay())
            ->first();

        if ($aggregate === null) {
            DailyUsageAggregate::query()->create($attributes);

            return;
        }

        $aggregate->update($attributes);
    }
}
