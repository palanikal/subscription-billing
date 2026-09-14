<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_authenticated_merchant_can_view_its_dashboard(): void
    {
        $merchant = Merchant::factory()->create();
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeader('X-API-Key', 'merchant-key')
            ->getJson("/api/v1/merchants/{$merchant->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.top_customers', [])
            ->assertJsonPath('data.projected_overage.revenue_cents', 0);
    }

    public function test_it_returns_404_when_a_merchant_requests_another_merchants_dashboard(): void
    {
        $merchant = Merchant::factory()->create();
        $otherMerchant = Merchant::factory()->create();
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeader('X-API-Key', 'merchant-key')
            ->getJson("/api/v1/merchants/{$otherMerchant->id}/dashboard")
            ->assertNotFound();
    }

    public function test_it_requires_an_api_key(): void
    {
        $merchant = Merchant::factory()->create();

        $this->getJson("/api/v1/merchants/{$merchant->id}/dashboard")
            ->assertUnauthorized();
    }

    private function createApiKey(Merchant $merchant, string $plainTextKey): void
    {
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', $plainTextKey),
        ]);

    }
}
