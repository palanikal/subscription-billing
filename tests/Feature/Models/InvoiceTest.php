<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_second_invoice_for_the_same_subscription_cycle(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create();
        Invoice::factory()->forSubscription($subscription)->create([
            'cycle_start' => '2026-09-01 00:00:00',
            'cycle_end' => '2026-10-01 00:00:00',
        ]);

        $this->expectException(QueryException::class);

        Invoice::factory()->forSubscription($subscription)->create([
            'cycle_start' => '2026-09-01 00:00:00',
            'cycle_end' => '2026-10-01 00:00:00',
        ]);
    }

    public function test_deleting_invoice_removes_its_line_items(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create();
        $invoice = Invoice::factory()->forSubscription($subscription)->create();
        $lineItem = InvoiceLineItem::factory()->for($invoice)->create();

        $invoice->delete();

        $this->assertDatabaseMissing('invoice_line_items', ['id' => $lineItem->id]);
    }
}
