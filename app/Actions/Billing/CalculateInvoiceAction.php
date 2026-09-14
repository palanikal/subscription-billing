<?php

namespace App\Actions\Billing;

use App\Models\DailyUsageAggregate;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use LogicException;

class CalculateInvoiceAction
{
    /**
     * Calculate an invoice preview without persisting it.
     *
     * Prices come from subscription-period snapshots, rather than the current
     * plan, so historical bills remain correct after a plan is edited.
     *
     * @return array{
     *     currency: string,
     *     cycle_start: CarbonImmutable,
     *     cycle_end: CarbonImmutable,
     *     subtotal_cents: int,
     *     total_cents: int,
     *     line_items: list<array{
     *         type: 'base'|'overage',
     *         description: string,
     *         period_start: CarbonImmutable,
     *         period_end: CarbonImmutable,
     *         quantity: int,
     *         unit_amount_cents: int,
     *         amount_cents: int,
     *         pricing_snapshot: array<string, int|string>
     *     }>
     * }
     */
    public function handle(Subscription $subscription, CarbonImmutable $cycleStart, CarbonImmutable $cycleEnd): array
    {
        $cycleStart = $cycleStart->utc();
        $cycleEnd = $cycleEnd->utc();

        if ($cycleEnd->lessThanOrEqualTo($cycleStart)) {
            throw new LogicException('The invoice cycle must end after it starts.');
        }

        $periods = $subscription->periods()
            ->where('starts_at', '<', $cycleEnd)
            ->where(function ($query) use ($cycleStart): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $cycleStart);
            })
            ->orderBy('starts_at')
            ->get();

        if ($periods->isEmpty()) {
            throw new LogicException('The subscription has no pricing period for this invoice cycle.');
        }

        $dailyUsage = DailyUsageAggregate::query()
            ->where('customer_id', $subscription->customer_id)
            ->where('usage_date', '>=', $cycleStart->toDateString())
            ->where('usage_date', '<', $cycleEnd->toDateString())
            ->get(['usage_date', 'total_units']);

        $cycleDays = $cycleStart->diffInDays($cycleEnd);
        $lineItems = [];

        foreach ($periods as $period) {
            [$periodStart, $periodEnd] = $this->overlappingSegment($period, $cycleStart, $cycleEnd);

            if ($periodStart->greaterThanOrEqualTo($periodEnd)) {
                continue;
            }

            $activeDays = $periodStart->diffInDays($periodEnd);
            $baseAmountCents = $this->prorate((int) $period->base_price_cents_snapshot, $activeDays, $cycleDays);
            $includedUnits = $this->prorateIncludedUnits((int) $period->included_units_snapshot, $activeDays, $cycleDays);
            $usageUnits = (int) $dailyUsage
                ->filter(function (DailyUsageAggregate $dailyAggregate) use ($periodStart, $periodEnd): bool {
                    $usageDate = $dailyAggregate->usage_date->toDateString();

                    return $usageDate >= $periodStart->toDateString()
                        && $usageDate < $periodEnd->toDateString();
                })
                ->sum('total_units');
            $overageUnits = max(0, $usageUnits - $includedUnits);
            $overageRateCents = (int) $period->overage_rate_cents_snapshot;

            $lineItems[] = [
                'type' => 'base',
                'description' => sprintf('%s base subscription', $period->plan_name_snapshot),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'quantity' => 1,
                'unit_amount_cents' => $baseAmountCents,
                'amount_cents' => $baseAmountCents,
                'pricing_snapshot' => [
                    'plan_name' => $period->plan_name_snapshot,
                    'monthly_base_price_cents' => (int) $period->base_price_cents_snapshot,
                    'active_days' => $activeDays,
                    'cycle_days' => $cycleDays,
                ],
            ];

            if ($overageUnits === 0) {
                continue;
            }

            $lineItems[] = [
                'type' => 'overage',
                'description' => sprintf('%s overage usage', $period->plan_name_snapshot),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'quantity' => $overageUnits,
                'unit_amount_cents' => $overageRateCents,
                'amount_cents' => $overageUnits * $overageRateCents,
                'pricing_snapshot' => [
                    'plan_name' => $period->plan_name_snapshot,
                    'included_units' => $includedUnits,
                    'usage_units' => $usageUnits,
                    'overage_rate_cents' => $overageRateCents,
                ],
            ];
        }

        $subtotalCents = (int) collect($lineItems)->sum('amount_cents');

        return [
            'currency' => 'USD',
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'subtotal_cents' => $subtotalCents,
            'total_cents' => $subtotalCents,
            'line_items' => $lineItems,
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function overlappingSegment(
        SubscriptionPeriod $period,
        CarbonImmutable $cycleStart,
        CarbonImmutable $cycleEnd,
    ): array {
        $periodStart = CarbonImmutable::instance($period->starts_at)->utc();
        $periodEnd = $period->ends_at === null
            ? $cycleEnd
            : CarbonImmutable::instance($period->ends_at)->utc();

        return [
            $periodStart->greaterThan($cycleStart) ? $periodStart : $cycleStart,
            $periodEnd->lessThan($cycleEnd) ? $periodEnd : $cycleEnd,
        ];
    }

    private function prorate(int $amount, int $activeDays, int $cycleDays): int
    {
        return intdiv(($amount * $activeDays) + intdiv($cycleDays, 2), $cycleDays);
    }

    private function prorateIncludedUnits(int $includedUnits, int $activeDays, int $cycleDays): int
    {
        return intdiv($includedUnits * $activeDays, $cycleDays);
    }
}
