<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'billing_cycle',
        'base_price_cents',
        'included_units',
        'overage_rate_cents',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'base_price_cents' => 'integer',
            'included_units' => 'integer',
            'overage_rate_cents' => 'integer',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function subscriptionPeriods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class);
    }
}
