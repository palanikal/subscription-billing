<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UsageEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_allows_same_idempotency_key_for_different_merchants(): void
    {
        $firstMerchant = Merchant::factory()->create();
        $firstCustomer = Customer::factory()->for($firstMerchant)->create();
        $secondMerchant = Merchant::factory()->create();
        $secondCustomer = Customer::factory()->for($secondMerchant)->create();

        $firstEvent = UsageEvent::factory()->forCustomer($firstCustomer)->create([
            'idempotency_key' => 'evt-retry-001',
        ]);
        $secondEvent = UsageEvent::factory()->forCustomer($secondCustomer)->create([
            'idempotency_key' => 'evt-retry-001',
        ]);

        $this->assertModelExists($firstEvent);
        $this->assertModelExists($secondEvent);
    }

    public function test_rejects_duplicate_idempotency_key_for_one_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        UsageEvent::factory()->forCustomer($customer)->create([
            'idempotency_key' => 'evt-retry-001',
        ]);

        $this->expectException(QueryException::class);

        UsageEvent::factory()->forCustomer($customer)->create([
            'idempotency_key' => 'evt-retry-001',
        ]);
    }

    public function test_rejects_second_daily_total_for_one_customer_and_date(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create();
        DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => '2026-09-12',
        ]);

        $this->expectException(QueryException::class);

        DailyUsageAggregate::factory()->forCustomer($customer)->create([
            'usage_date' => '2026-09-12',
        ]);
    }
}
