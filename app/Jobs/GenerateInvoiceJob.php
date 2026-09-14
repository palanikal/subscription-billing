<?php

namespace App\Jobs;

use App\Actions\Billing\GenerateInvoiceAction;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 86_400;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public int $subscriptionId,
        public string $cycleStart,
        public string $cycleEnd,
    ) {
        $this->onQueue('billing');
    }

    public function handle(GenerateInvoiceAction $generateInvoice): void
    {
        $invoice = $generateInvoice->handle(
            Subscription::query()->findOrFail($this->subscriptionId),
            CarbonImmutable::parse($this->cycleStart, 'UTC'),
            CarbonImmutable::parse($this->cycleEnd, 'UTC'),
        );

        if ($invoice->status !== 'finalized') {
            $invoice->update(['status' => 'finalized']);
        }
    }

    public function uniqueId(): string
    {
        return "generate-invoice:{$this->subscriptionId}:{$this->cycleStart}:{$this->cycleEnd}";
    }
}
