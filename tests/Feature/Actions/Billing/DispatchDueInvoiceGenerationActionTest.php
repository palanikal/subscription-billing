<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\DispatchDueInvoiceGenerationAction;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueInvoiceGenerationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_completed_cycles_that_do_not_already_have_an_invoice(): void
    {
        $dueSubscription = $this->subscriptionForCycle('2026-09-01 00:00:00', '2026-10-01 00:00:00');
        $this->subscriptionForCycle('2026-10-01 00:00:00', '2026-11-01 00:00:00');
        $billedSubscription = $this->subscriptionForCycle('2026-09-01 00:00:00', '2026-10-01 00:00:00');
        Invoice::factory()->forSubscription($billedSubscription)->create([
            'cycle_start' => '2026-09-01 00:00:00',
            'cycle_end' => '2026-10-01 00:00:00',
        ]);
        Queue::fake([GenerateInvoiceJob::class]);

        $dispatched = app(DispatchDueInvoiceGenerationAction::class)->handle(
            CarbonImmutable::parse('2026-10-02 00:00:00', 'UTC'),
        );

        $this->assertSame(1, $dispatched);
        Queue::assertPushed(GenerateInvoiceJob::class, function (GenerateInvoiceJob $job) use ($dueSubscription): bool {
            return $job->subscriptionId === $dueSubscription->id
                && $job->cycleStart === '2026-09-01 00:00:00'
                && $job->cycleEnd === '2026-10-01 00:00:00';
        });
    }

    private function subscriptionForCycle(string $cycleStart, string $cycleEnd): Subscription
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();

        return Subscription::factory()->forCustomer($customer)->create([
            'current_period_start' => $cycleStart,
            'current_period_end' => $cycleEnd,
        ]);

    }
}
