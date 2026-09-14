<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecordUsageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_usage_event_for_authenticated_merchants_customer(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->for($merchant)->create([
            'external_id' => 'cust_acme_001',
        ]);
        $this->createApiKey($merchant, 'merchant-key');

        $response = $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', [
                'merchant_id' => 999_999,
                'customer_external_id' => 'cust_acme_001',
                'quantity' => 42,
                'occurred_at' => '2026-09-12T08:30:00Z',
                'metadata' => ['source' => 'api'],
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('meta.duplicate', false);

        $this->assertDatabaseHas('usage_events', [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'idempotency_key' => 'event-001',
            'quantity' => 42,
        ]);

        $usageEvent = UsageEvent::query()->firstOrFail();

        $this->assertSame('2026-09-12', $usageEvent->occurred_on->toDateString());
    }

    public function test_returns_original_event_when_same_request_is_retried(): void
    {
        $merchant = Merchant::factory()->create();
        Customer::factory()->for($merchant)->create([
            'external_id' => 'cust_acme_001',
        ]);
        $this->createApiKey($merchant, 'merchant-key');
        $payload = $this->usagePayload();

        $firstResponse = $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', $payload);
        $secondResponse = $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', $payload);

        $firstResponse
            ->assertAccepted()
            ->assertJsonPath('meta.duplicate', false);
        $secondResponse
            ->assertAccepted()
            ->assertJsonPath('meta.duplicate', true)
            ->assertJsonPath('data.usage_event_id', $firstResponse->json('data.usage_event_id'));
        $this->assertSame(1, UsageEvent::count());
    }

    public function test_returns_409_when_idempotency_key_has_different_payload(): void
    {
        $merchant = Merchant::factory()->create();
        Customer::factory()->for($merchant)->create([
            'external_id' => 'cust_acme_001',
        ]);
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', $this->usagePayload())
            ->assertAccepted();

        $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', $this->usagePayload(['quantity' => 43]))
            ->assertConflict();

        $this->assertSame(1, UsageEvent::count());
    }

    public function test_returns_404_when_customer_belongs_to_another_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $otherMerchant = Merchant::factory()->create();
        Customer::factory()->for($otherMerchant)->create([
            'external_id' => 'cust_other_001',
        ]);
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeaders($this->headersFor('merchant-key', 'event-001'))
            ->postJson('/api/v1/usage', $this->usagePayload([
                'customer_external_id' => 'cust_other_001',
            ]))
            ->assertNotFound();
    }

    public function test_returns_422_when_idempotency_header_is_missing(): void
    {
        $merchant = Merchant::factory()->create();
        Customer::factory()->for($merchant)->create([
            'external_id' => 'cust_acme_001',
        ]);
        $this->createApiKey($merchant, 'merchant-key');

        $this->withHeader('X-API-Key', 'merchant-key')
            ->postJson('/api/v1/usage', $this->usagePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(string $apiKey, string $idempotencyKey): array
    {
        return [
            'X-API-Key' => $apiKey,
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function createApiKey(Merchant $merchant, string $plainTextKey): void
    {
        ApiKey::factory()->for($merchant)->create([
            'key_hash' => hash('sha256', $plainTextKey),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function usagePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_external_id' => 'cust_acme_001',
            'quantity' => 42,
            'occurred_at' => '2026-09-12T08:30:00Z',
            'metadata' => ['source' => 'api'],
        ], $overrides);
    }
}
