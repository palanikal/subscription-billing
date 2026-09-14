<?php

namespace App\Actions\Billing;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DispatchDueInvoiceGenerationAction
{
    /**
     * Queue one invoice job for every active subscription with an unbilled,
     * completed billing cycle.
     */
    public function handle(CarbonImmutable $asOf): int
    {
        $asOf = $asOf->utc();
        $subscriptionsTable = (new Subscription)->getTable();
        $invoicesTable = (new Invoice)->getTable();
        $dispatched = 0;

        Subscription::query()
            ->select(['id', 'current_period_start', 'current_period_end'])
            ->where('status', 'active')
            ->whereNotNull('current_period_start')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $asOf)
            ->whereDoesntHave('invoices', function (Builder $query) use ($invoicesTable, $subscriptionsTable): void {
                $query->whereColumn("{$invoicesTable}.cycle_start", "{$subscriptionsTable}.current_period_start")
                    ->whereColumn("{$invoicesTable}.cycle_end", "{$subscriptionsTable}.current_period_end");
            })
            ->lazyById(200)
            ->each(function (Subscription $subscription) use (&$dispatched): void {
                if ($subscription->current_period_start === null || $subscription->current_period_end === null) {
                    return;
                }

                GenerateInvoiceJob::dispatch(
                    $subscription->id,
                    $subscription->current_period_start->utc()->toDateTimeString(),
                    $subscription->current_period_end->utc()->toDateTimeString(),
                );

                $dispatched++;
            });

        return $dispatched;
    }
}
