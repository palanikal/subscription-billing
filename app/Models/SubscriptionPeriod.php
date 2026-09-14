<?php

namespace App\Models;

use Database\Factories\SubscriptionPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPeriod extends Model
{
    /** @use HasFactory<SubscriptionPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'merchant_id',
        'customer_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'plan_name_snapshot',
        'base_price_cents_snapshot',
        'included_units_snapshot',
        'overage_rate_cents_snapshot',
        'billing_cycle_snapshot',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'base_price_cents_snapshot' => 'integer',
            'included_units_snapshot' => 'integer',
            'overage_rate_cents_snapshot' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
