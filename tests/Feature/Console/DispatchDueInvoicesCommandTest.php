<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDueInvoicesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_queues_invoices_for_completed_cycles_at_the_requested_time(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create([
            'current_period_start' => '2026-08-01 00:00:00',
            'current_period_end' => '2026-09-01 00:00:00',
        ]);
        Queue::fake([GenerateInvoiceJob::class]);

        $this->artisan('billing:dispatch-due-invoices', ['--as-of' => '2026-09-02 00:00:00'])
            ->expectsOutput('Dispatched 1 invoice job(s).')
            ->assertExitCode(0);

        Queue::assertPushed(GenerateInvoiceJob::class, function (GenerateInvoiceJob $job) use ($subscription): bool {
            return $job->subscriptionId === $subscription->id
                && $job->cycleStart === '2026-08-01 00:00:00'
                && $job->cycleEnd === '2026-09-01 00:00:00';
        });

    }
}
