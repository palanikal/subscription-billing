<?php

namespace App\Actions\Usage;

use App\Models\DailyUsageAggregate;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AggregateUsageForDateAction
{
    private const UPSERT_CHUNK_SIZE = 500;

    /**
     * Recompute one UTC day's customer totals from the immutable usage-event log.
     */
    public function handle(CarbonImmutable $usageDate): int
    {
        $aggregatedAt = now('UTC');
        $date = $usageDate->toDateString();
        $rows = [];
        $processedRows = 0;
        $usageEventsTable = (new UsageEvent)->getTable();

        $groups = DB::table($usageEventsTable)
            ->selectRaw('merchant_id, customer_id, SUM(quantity) as total_units, COUNT(*) as event_count, MAX(id) as last_event_id')
            ->whereBetween('occurred_on', [$usageDate->startOfDay(), $usageDate->endOfDay()])
            ->groupBy('merchant_id', 'customer_id')
            ->orderBy('merchant_id')
            ->orderBy('customer_id')
            ->cursor();

        foreach ($groups as $group) {
            $rows[] = [
                'merchant_id' => $group->merchant_id,
                'customer_id' => $group->customer_id,
                'usage_date' => $date,
                'total_units' => $group->total_units,
                'event_count' => $group->event_count,
                'last_aggregated_event_id' => $group->last_event_id,
                'aggregated_at' => $aggregatedAt,
                'created_at' => $aggregatedAt,
                'updated_at' => $aggregatedAt,
            ];

            if (count($rows) === self::UPSERT_CHUNK_SIZE) {
                $this->upsert($rows);
                $processedRows += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->upsert($rows);
            $processedRows += count($rows);
        }

        return $processedRows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsert(array $rows): void
    {
        DailyUsageAggregate::query()->upsert(
            $rows,
            ['customer_id', 'usage_date'],
            [
                'merchant_id',
                'total_units',
                'event_count',
                'last_aggregated_event_id',
                'aggregated_at',
                'updated_at',
            ],
        );
    }
}
