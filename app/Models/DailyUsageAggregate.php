<?php

namespace App\Models;

use Database\Factories\DailyUsageAggregateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsageAggregate extends Model
{
    /** @use HasFactory<DailyUsageAggregateFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'usage_date',
        'total_units',
        'event_count',
        'last_aggregated_event_id',
        'aggregated_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'total_units' => 'integer',
            'event_count' => 'integer',
            'last_aggregated_event_id' => 'integer',
            'aggregated_at' => 'datetime',
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
}
