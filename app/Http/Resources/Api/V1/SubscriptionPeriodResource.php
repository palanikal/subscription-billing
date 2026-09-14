<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subscription_period_id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'plan_id' => $this->plan_id,
            'plan_name' => $this->plan_name_snapshot,
            'effective_at' => $this->starts_at?->utc()->toIso8601String(),
            'base_price_cents' => $this->base_price_cents_snapshot,
            'included_units' => $this->included_units_snapshot,
            'overage_rate_cents' => $this->overage_rate_cents_snapshot,
            'change_reason' => $this->change_reason,
        ];
    }
}
