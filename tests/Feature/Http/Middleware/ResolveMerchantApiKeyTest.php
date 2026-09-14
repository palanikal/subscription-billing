<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveMerchantApiKeyTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('merchant-api-key')->get('/api/test-authentication', function (Request $request): array {
            return [
                'data' => [
                    'merchant_id' => $request->attributes->get('merchant')->id,
                ],
            ];
        });

        Route::middleware(['merchant-api-key', 'throttle:usage-ingestion'])->get('/api/test-throttle', function (): array {
            return ['data' => ['status' => 'accepted']];
        });
    }

    public function test_returns_401_when_api_key_is_missing(): void
    {
        $this->getJson('/api/test-authentication')->assertUnauthorized();
    }

    public function test_returns_401_when_api_key_is_invalid_or_revoked(): void
    {
        $merchant = Merchant::factory()->create();
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', 'revoked-key'),
            'revoked_at' => now(),
        ]);

        $this->withHeader('X-API-Key', 'invalid-key')
            ->getJson('/api/test-authentication')
            ->assertUnauthorized();

        $this->withHeader('X-API-Key', 'revoked-key')
            ->getJson('/api/test-authentication')
            ->assertUnauthorized();
    }

    public function test_resolves_merchant_from_valid_api_key(): void
    {
        $merchant = Merchant::factory()->create();
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', 'valid-key'),
        ]);

        $this->withHeader('X-API-Key', 'valid-key')
            ->getJson('/api/test-authentication')
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id);
    }

    public function test_returns_429_after_120_requests_for_one_api_key(): void
    {
        $merchant = Merchant::factory()->create();
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', 'limited-key'),
        ]);

        for ($requestNumber = 1; $requestNumber <= 120; $requestNumber++) {
            $this->withHeader('X-API-Key', 'limited-key')
                ->getJson('/api/test-throttle')
                ->assertOk();
        }

        $this->withHeader('X-API-Key', 'limited-key')
            ->getJson('/api/test-throttle')
            ->assertTooManyRequests();
    }
}
