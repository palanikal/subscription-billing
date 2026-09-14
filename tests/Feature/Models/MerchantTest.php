<?php

namespace Tests\Feature\Models;

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_merchant_owns_its_api_keys_plans_and_customers(): void
    {
        $merchant = Merchant::factory()->create();
        $apiKey = ApiKey::factory()->for($merchant)->create();
        $plan = Plan::factory()->for($merchant)->create();
        $customer = Customer::factory()->for($merchant)->create();

        $this->assertTrue($merchant->apiKeys()->whereKey($apiKey->id)->exists());
        $this->assertTrue($merchant->plans()->whereKey($plan->id)->exists());
        $this->assertTrue($merchant->customers()->whereKey($customer->id)->exists());
    }

    public function test_deleting_merchant_removes_its_catalog_records(): void
    {
        $merchant = Merchant::factory()->create();
        $apiKey = ApiKey::factory()->for($merchant)->create();
        $plan = Plan::factory()->for($merchant)->create();
        $customer = Customer::factory()->for($merchant)->create();

        $merchant->delete();

        $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id]);
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
