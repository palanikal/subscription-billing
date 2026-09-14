<?php

namespace App\Jobs;

use App\Actions\Usage\AggregateUsageForDateAction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateDailyUsageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public string $usageDate)
    {
        $this->onQueue('usage');
    }

    public function handle(AggregateUsageForDateAction $aggregateUsage): void
    {
        $aggregateUsage->handle(CarbonImmutable::parse($this->usageDate, 'UTC')->startOfDay());
    }

    public function uniqueId(): string
    {
        return "aggregate-daily-usage:{$this->usageDate}";
    }
}
