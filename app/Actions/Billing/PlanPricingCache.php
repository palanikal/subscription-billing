<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

class PlanPricingCache
{
    private const TTL_SECONDS = 3600;

    /**
     * Return the current plan attributes used when creating a pricing snapshot.
     *
     * @return array{plan_id: int, name: string, billing_cycle: string, base_price_cents: int, included_units: int, overage_rate_cents: int}
     */
    public function for(Plan $plan): array
    {
        return Cache::remember(
            $this->key($plan),
            self::TTL_SECONDS,
            fn (): array => [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'billing_cycle' => $plan->billing_cycle,
                'base_price_cents' => $plan->base_price_cents,
                'included_units' => $plan->included_units,
                'overage_rate_cents' => $plan->overage_rate_cents,
            ],
        );
    }

    public function forget(Plan $plan): void
    {
        Cache::forget($this->key($plan));
    }

    public function key(Plan $plan): string
    {
        return "plan-pricing:merchant:{$plan->merchant_id}:plan:{$plan->id}";
    }
}
