<?php

namespace App\Actions\Dashboard;

use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildMerchantDashboardAction
{
    /**
     * Build merchant reporting from daily aggregates, never from raw events.
     *
     * @return array{
     *     merchant_id: int,
     *     period: array{month_start: string, as_of: string},
     *     top_customers: list<array{customer_id: int, external_id: string, name: string|null, usage_units: int}>,
     *     projected_overage: array{revenue_cents: int, subscriptions_with_projected_overage: int, method: string},
     *     churn_risk_customers: list<array{customer_id: int, external_id: string, name: string|null, current_usage_units: int, previous_usage_units: int, usage_drop_percent: int}>
     * }
     */
    public function handle(Merchant $merchant, CarbonImmutable $asOf): array
    {
        $asOf = $asOf->utc();
        $monthStart = $asOf->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->addMonthNoOverflow();
        $usageWindowEnd = min($asOf->startOfDay()->addDay(), $monthEnd);
        $elapsedDays = max(1, $monthStart->diffInDays($usageWindowEnd));
        $previousMonthStart = $monthStart->subMonthNoOverflow();
        $previousWindowEnd = $previousMonthStart->addDays($elapsedDays);

        $currentDailyUsage = $this->dailyUsageForRange($merchant, $monthStart, $usageWindowEnd);
        $previousDailyUsage = $this->dailyUsageForRange($merchant, $previousMonthStart, $previousWindowEnd);
        $currentUsageByCustomer = $this->usageByCustomer($currentDailyUsage);
        $previousUsageByCustomer = $this->usageByCustomer($previousDailyUsage);
        $customers = $this->customersById($merchant, $currentUsageByCustomer, $previousUsageByCustomer);

        return [
            'merchant_id' => $merchant->id,
            'period' => [
                'month_start' => $monthStart->toDateString(),
                'as_of' => $asOf->toIso8601String(),
            ],
            'top_customers' => $this->topCustomers($currentUsageByCustomer, $customers),
            'projected_overage' => $this->projectedOverage(
                $merchant,
                $currentDailyUsage,
                $monthStart,
                $monthEnd,
                $usageWindowEnd,
            ),
            'churn_risk_customers' => $this->churnRiskCustomers(
                $currentUsageByCustomer,
                $previousUsageByCustomer,
                $customers,
            ),
        ];
    }

    /** @return Collection<int, DailyUsageAggregate> */
    private function dailyUsageForRange(Merchant $merchant, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return DailyUsageAggregate::query()
            ->whereBelongsTo($merchant)
            ->where('usage_date', '>=', $start->toDateString())
            ->where('usage_date', '<', $end->toDateString())
            ->get(['customer_id', 'usage_date', 'total_units']);
    }

    /** @return Collection<int, int> */
    private function usageByCustomer(Collection $dailyUsage): Collection
    {
        return $dailyUsage
            ->groupBy('customer_id')
            ->map(fn (Collection $aggregates): int => (int) $aggregates->sum('total_units'));
    }

    /** @return Collection<int, Customer> */
    private function customersById(Merchant $merchant, Collection $currentUsage, Collection $previousUsage): Collection
    {
        $customerIds = $currentUsage->keys()
            ->merge($previousUsage->keys())
            ->unique()
            ->values();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Customer::query()
            ->whereBelongsTo($merchant)
            ->whereKey($customerIds)
            ->get(['id', 'external_id', 'name'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, int>  $currentUsage
     * @param  Collection<int, Customer>  $customers
     * @return list<array{customer_id: int, external_id: string, name: string|null, usage_units: int}>
     */
    private function topCustomers(Collection $currentUsage, Collection $customers): array
    {
        return $currentUsage
            ->map(fn (int $usageUnits, int $customerId): array => [
                'customer_id' => $customerId,
                'usage_units' => $usageUnits,
            ])
            ->sort(function (array $left, array $right): int {
                return ($right['usage_units'] <=> $left['usage_units'])
                    ?: ($left['customer_id'] <=> $right['customer_id']);
            })
            ->take(5)
            ->map(function (array $usage) use ($customers): array {
                $customer = $customers->get($usage['customer_id']);

                return [
                    'customer_id' => $usage['customer_id'],
                    'external_id' => $customer?->external_id ?? '',
                    'name' => $customer?->name,
                    'usage_units' => $usage['usage_units'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DailyUsageAggregate>  $currentDailyUsage
     * @return array{revenue_cents: int, subscriptions_with_projected_overage: int, method: string}
     */
    private function projectedOverage(
        Merchant $merchant,
        Collection $currentDailyUsage,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        CarbonImmutable $usageWindowEnd,
    ): array {
        $usageByCustomerAndDate = $currentDailyUsage
            ->groupBy('customer_id')
            ->map(fn (Collection $aggregates): Collection => $aggregates->keyBy(
                fn (DailyUsageAggregate $aggregate): string => $aggregate->usage_date->toDateString(),
            ));
        $subscriptions = Subscription::query()
            ->whereBelongsTo($merchant)
            ->where('status', 'active')
            ->whereNotNull('current_period_start')
            ->whereNotNull('current_period_end')
            ->with(['periods' => function ($query) use ($monthStart, $monthEnd): void {
                $query->select([
                    'id', 'subscription_id', 'starts_at', 'ends_at', 'included_units_snapshot', 'overage_rate_cents_snapshot',
                ])
                    ->where('starts_at', '<', $monthEnd)
                    ->where(function ($query) use ($monthStart): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', $monthStart);
                    })
                    ->orderBy('starts_at');
            }])
            ->get(['id', 'customer_id', 'current_period_start', 'current_period_end']);
        $revenueCents = 0;
        $subscriptionsWithProjectedOverage = 0;

        foreach ($subscriptions as $subscription) {
            $cycleStart = max(CarbonImmutable::instance($subscription->current_period_start)->utc(), $monthStart);
            $cycleEnd = min(CarbonImmutable::instance($subscription->current_period_end)->utc(), $monthEnd);
            $observedCycleEnd = min($cycleEnd, $usageWindowEnd);
            $cycleDays = $cycleStart->diffInDays($cycleEnd);
            $elapsedCycleDays = $cycleStart->diffInDays($observedCycleEnd);

            if ($cycleDays < 1 || $elapsedCycleDays < 1) {
                continue;
            }

            $subscriptionRevenueCents = 0;

            foreach ($subscription->periods as $period) {
                $subscriptionRevenueCents += $this->projectedPeriodOverage(
                    $period,
                    $usageByCustomerAndDate->get($subscription->customer_id, collect()),
                    $cycleStart,
                    $cycleEnd,
                    $usageWindowEnd,
                    $cycleDays,
                    $elapsedCycleDays,
                );
            }

            $revenueCents += $subscriptionRevenueCents;
            $subscriptionsWithProjectedOverage += $subscriptionRevenueCents > 0 ? 1 : 0;
        }

        return [
            'revenue_cents' => $revenueCents,
            'subscriptions_with_projected_overage' => $subscriptionsWithProjectedOverage,
            'method' => 'Month-to-date pace is projected across each observed pricing segment; this is an estimate, not a charge.',
        ];
    }

    /** @param Collection<string, DailyUsageAggregate> $usageByDate */
    private function projectedPeriodOverage(
        SubscriptionPeriod $period,
        Collection $usageByDate,
        CarbonImmutable $cycleStart,
        CarbonImmutable $cycleEnd,
        CarbonImmutable $usageWindowEnd,
        int $cycleDays,
        int $elapsedCycleDays,
    ): int {
        $segmentStart = max(CarbonImmutable::instance($period->starts_at)->utc(), $cycleStart);
        $segmentEnd = min(
            $period->ends_at === null ? $cycleEnd : CarbonImmutable::instance($period->ends_at)->utc(),
            $cycleEnd,
        );
        $observedSegmentEnd = min($segmentEnd, $usageWindowEnd);

        if ($segmentStart->greaterThanOrEqualTo($observedSegmentEnd)) {
            return 0;
        }

        $segmentDays = $segmentStart->diffInDays($segmentEnd);
        $usageUnits = (int) $usageByDate
            ->filter(function (DailyUsageAggregate $aggregate) use ($segmentStart, $observedSegmentEnd): bool {
                $usageDate = $aggregate->usage_date->toDateString();

                return $usageDate >= $segmentStart->toDateString()
                    && $usageDate < $observedSegmentEnd->toDateString();
            })
            ->sum('total_units');
        $includedUnits = intdiv((int) $period->included_units_snapshot * $segmentDays, $cycleDays);
        $projectedUsageUnits = $this->ceilDivide($usageUnits * $cycleDays, $elapsedCycleDays);
        $projectedOverageUnits = max(0, $projectedUsageUnits - $includedUnits);

        return $projectedOverageUnits * (int) $period->overage_rate_cents_snapshot;
    }

    private function ceilDivide(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }

    /**
     * @param  Collection<int, int>  $currentUsage
     * @param  Collection<int, int>  $previousUsage
     * @param  Collection<int, Customer>  $customers
     * @return list<array{customer_id: int, external_id: string, name: string|null, current_usage_units: int, previous_usage_units: int, usage_drop_percent: int}>
     */
    private function churnRiskCustomers(Collection $currentUsage, Collection $previousUsage, Collection $customers): array
    {
        return $previousUsage
            ->filter(function (int $previousUsageUnits, int $customerId) use ($currentUsage): bool {
                return $previousUsageUnits > 0 && (($currentUsage->get($customerId, 0) * 2) < $previousUsageUnits);
            })
            ->map(function (int $previousUsageUnits, int $customerId) use ($currentUsage, $customers): array {
                $currentUsageUnits = (int) $currentUsage->get($customerId, 0);
                $customer = $customers->get($customerId);

                return [
                    'customer_id' => $customerId,
                    'external_id' => $customer?->external_id ?? '',
                    'name' => $customer?->name,
                    'current_usage_units' => $currentUsageUnits,
                    'previous_usage_units' => $previousUsageUnits,
                    'usage_drop_percent' => intdiv(($previousUsageUnits - $currentUsageUnits) * 100, $previousUsageUnits),
                ];
            })
            ->sort(function (array $left, array $right): int {
                return ($right['usage_drop_percent'] <=> $left['usage_drop_percent'])
                    ?: ($left['customer_id'] <=> $right['customer_id']);
            })
            ->values()
            ->all();
    }
}
