<?php

namespace Tests\Feature;

use Database\Seeders\DemoBillingSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_displays_the_seeded_merchant_dashboard(): void
    {
        $this->seed(DemoBillingSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Subscription intelligence')
            ->assertSee('Palani Analytics')
            ->assertSee('Top customers');
    }
}
