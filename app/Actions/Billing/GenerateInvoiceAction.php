<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceAction
{
    public function __construct(private CalculateInvoiceAction $calculateInvoice) {}

    /**
     * Generate a draft invoice once for a subscription billing cycle.
     *
     * The subscription lock serializes normal concurrent requests. The unique
     * invoice-cycle index is the database-level backstop against duplicate bills.
     */
    public function handle(Subscription $subscription, CarbonImmutable $cycleStart, CarbonImmutable $cycleEnd): Invoice
    {
        $cycleStart = $cycleStart->utc();
        $cycleEnd = $cycleEnd->utc();

        return DB::transaction(function () use ($subscription, $cycleStart, $cycleEnd): Invoice {
            $lockedSubscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingInvoice = $lockedSubscription->invoices()
                ->with('lineItems')
                ->where('cycle_start', $cycleStart)
                ->where('cycle_end', $cycleEnd)
                ->first();

            if ($existingInvoice !== null) {
                return $existingInvoice;
            }

            $draft = $this->calculateInvoice->handle($lockedSubscription, $cycleStart, $cycleEnd);

            $invoice = Invoice::query()->create([
                'merchant_id' => $lockedSubscription->merchant_id,
                'customer_id' => $lockedSubscription->customer_id,
                'subscription_id' => $lockedSubscription->id,
                'cycle_start' => $draft['cycle_start'],
                'cycle_end' => $draft['cycle_end'],
                'currency' => $draft['currency'],
                'status' => 'draft',
                'subtotal_cents' => $draft['subtotal_cents'],
                'total_cents' => $draft['total_cents'],
                'generated_at' => now('UTC'),
                'metadata' => [
                    'calculator_version' => 1,
                    'line_item_count' => count($draft['line_items']),
                ],
            ]);

            $invoice->lineItems()->createMany($draft['line_items']);

            return $invoice->load('lineItems');
        }, attempts: 3);
    }
}
