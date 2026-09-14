<?php

namespace App\Actions\Billing;

use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSubscriptionPlanAction
{
    public function __construct(private PlanPricingCache $planPricingCache) {}

    /**
     * Close the active pricing period and create a new immutable plan snapshot.
     */
    public function handle(
        Merchant $merchant,
        Subscription $subscription,
        int $planId,
        CarbonImmutable $effectiveAt,
    ): SubscriptionPeriod {
        $effectiveAt = $effectiveAt->utc();

        if (! $effectiveAt->equalTo($effectiveAt->startOfDay())) {
            throw ValidationException::withMessages([
                'effective_at' => 'Plan changes must take effect at 00:00 UTC.',
            ]);
        }

        return DB::transaction(function () use ($merchant, $subscription, $planId, $effectiveAt): SubscriptionPeriod {
            $lockedSubscription = Subscription::query()
                ->whereBelongsTo($merchant)
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            $plan = Plan::query()
                ->whereBelongsTo($merchant)
                ->whereKey($planId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSubscriptionCanChangePlans($lockedSubscription, $effectiveAt);

            $currentPeriod = $lockedSubscription->periods()
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->first();

            if ($currentPeriod === null) {
                throw ValidationException::withMessages([
                    'subscription' => 'The subscription has no active pricing period.',
                ]);
            }

            $currentPeriodStart = CarbonImmutable::instance($currentPeriod->starts_at)->utc();

            if (! $effectiveAt->greaterThan($currentPeriodStart)) {
                throw ValidationException::withMessages([
                    'effective_at' => 'Plan changes must be after the active pricing period starts.',
                ]);
            }

            if ($currentPeriod->plan_id === $plan->id) {
                throw ValidationException::withMessages([
                    'plan_id' => 'The subscription is already using this plan.',
                ]);
            }

            $pricing = $this->planPricingCache->for($plan);

            $currentPeriod->update(['ends_at' => $effectiveAt]);

            return SubscriptionPeriod::query()->create([
                'subscription_id' => $lockedSubscription->id,
                'merchant_id' => $merchant->id,
                'customer_id' => $lockedSubscription->customer_id,
                'plan_id' => $plan->id,
                'starts_at' => $effectiveAt,
                'ends_at' => null,
                'plan_name_snapshot' => $pricing['name'],
                'base_price_cents_snapshot' => $pricing['base_price_cents'],
                'included_units_snapshot' => $pricing['included_units'],
                'overage_rate_cents_snapshot' => $pricing['overage_rate_cents'],
                'billing_cycle_snapshot' => $pricing['billing_cycle'],
                'change_reason' => $this->changeReason($currentPeriod, $plan),
            ]);
        }, attempts: 3);
    }

    private function ensureSubscriptionCanChangePlans(Subscription $subscription, CarbonImmutable $effectiveAt): void
    {
        if ($subscription->status !== 'active') {
            throw ValidationException::withMessages([
                'subscription' => 'Only active subscriptions can change plans.',
            ]);
        }

        if ($subscription->current_period_start === null || $subscription->current_period_end === null) {
            throw ValidationException::withMessages([
                'subscription' => 'The subscription has no active billing cycle.',
            ]);
        }

        $cycleStart = CarbonImmutable::instance($subscription->current_period_start)->utc();
        $cycleEnd = CarbonImmutable::instance($subscription->current_period_end)->utc();

        if (! $effectiveAt->greaterThan($cycleStart) || ! $effectiveAt->lessThan($cycleEnd)) {
            throw ValidationException::withMessages([
                'effective_at' => 'Plan changes must be inside the current billing cycle.',
            ]);
        }
    }

    private function changeReason(SubscriptionPeriod $currentPeriod, Plan $plan): string
    {
        return match (true) {
            $plan->base_price_cents > $currentPeriod->base_price_cents_snapshot => 'upgrade',
            $plan->base_price_cents < $currentPeriod->base_price_cents_snapshot => 'downgrade',
            default => 'plan_change',
        };
    }
}
