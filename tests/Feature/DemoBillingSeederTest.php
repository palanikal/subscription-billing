<?php

namespace Tests\Feature;

use Database\Seeders\DemoBillingSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoBillingSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_repeatable_demo_billing_data(): void
    {
        $this->seed(DemoBillingSeeder::class);
        $this->seed(DemoBillingSeeder::class);

        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('api_keys', 1);
        $this->assertDatabaseCount('plans', 2);
        $this->assertDatabaseCount('customers', 4);
        $this->assertDatabaseCount('subscriptions', 4);
        $this->assertDatabaseCount('subscription_periods', 4);
        $this->assertDatabaseCount('daily_usage_aggregates', 7);
        $this->assertDatabaseHas('customers', ['external_id' => 'cust_billing_demo']);
    }
}
