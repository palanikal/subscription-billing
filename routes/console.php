<?php

use App\Actions\Billing\DispatchDueInvoiceGenerationAction;
use App\Jobs\AggregateDailyUsageJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $today = now('UTC')->startOfDay();

    foreach (range(0, 2) as $daysAgo) {
        AggregateDailyUsageJob::dispatch($today->subDays($daysAgo)->toDateString());
    }
})
    ->hourly()
    ->name('aggregate-recent-usage')
    ->withoutOverlapping(15)
    ->onOneServer();

Schedule::call(function (DispatchDueInvoiceGenerationAction $dispatchDueInvoices): void {
    $dispatchDueInvoices->handle(now('UTC')->toImmutable());
})
    ->dailyAt('00:15')
    ->timezone('UTC')
    ->name('dispatch-due-invoices')
    ->withoutOverlapping(30)
    ->onOneServer();
