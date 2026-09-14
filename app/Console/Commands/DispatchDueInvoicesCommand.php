<?php

namespace App\Console\Commands;

use App\Actions\Billing\DispatchDueInvoiceGenerationAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:dispatch-due-invoices {--as-of= : UTC date/time used to find completed cycles}')]
#[Description('Queue invoices for completed, unbilled subscription cycles.')]
class DispatchDueInvoicesCommand extends Command
{
    public function handle(DispatchDueInvoiceGenerationAction $dispatchDueInvoices): int
    {
        $asOf = $this->option('as-of');
        $dispatched = $dispatchDueInvoices->handle(
            $asOf === null
                ? now('UTC')->toImmutable()
                : CarbonImmutable::parse((string) $asOf, 'UTC'),
        );

        $this->info("Dispatched {$dispatched} invoice job(s).");

        return self::SUCCESS;
    }
}
