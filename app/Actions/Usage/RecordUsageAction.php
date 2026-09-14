<?php

namespace App\Actions\Usage;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordUsageAction
{
    /**
     * @param  array{customer_external_id: string, idempotency_key: string, metadata?: array<string, mixed>, occurred_at: string, quantity: int}  $attributes
     * @return array{duplicate: bool, usage_event: UsageEvent}
     */
    public function handle(Merchant $merchant, array $attributes): array
    {
        $customer = Customer::query()
            ->whereBelongsTo($merchant)
            ->where('external_id', $attributes['customer_external_id'])
            ->firstOrFail();

        $occurredAt = CarbonImmutable::parse($attributes['occurred_at'])->utc();
        $payloadHash = $this->payloadHash($attributes, $occurredAt);

        $existingEvent = UsageEvent::query()
            ->where('merchant_id', $merchant->id)
            ->where('idempotency_key', $attributes['idempotency_key'])
            ->first();

        if ($existingEvent !== null) {
            return $this->existingEventResult($existingEvent, $payloadHash);
        }

        try {
            $usageEvent = UsageEvent::query()->create([
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'idempotency_key' => $attributes['idempotency_key'],
                'payload_hash' => $payloadHash,
                'quantity' => $attributes['quantity'],
                'occurred_at' => $occurredAt,
                'occurred_on' => $occurredAt->toDateString(),
                'metadata' => $attributes['metadata'] ?? null,
            ]);
        } catch (QueryException $exception) {
            $existingEvent = UsageEvent::query()
                ->where('merchant_id', $merchant->id)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($existingEvent === null) {
                throw $exception;
            }

            return $this->existingEventResult($existingEvent, $payloadHash);
        }

        return [
            'duplicate' => false,
            'usage_event' => $usageEvent,
        ];
    }

    /**
     * @param  array{customer_external_id: string, idempotency_key: string, metadata?: array<string, mixed>, occurred_at: string, quantity: int}  $attributes
     */
    private function payloadHash(array $attributes, CarbonImmutable $occurredAt): string
    {
        $metadata = $attributes['metadata'] ?? null;

        if (is_array($metadata)) {
            $metadata = $this->sortRecursively($metadata);
        }

        $payload = [
            'customer_external_id' => $attributes['customer_external_id'],
            'quantity' => $attributes['quantity'],
            'occurred_at' => $occurredAt->format('Y-m-d\\TH:i:s.u\\Z'),
            'metadata' => $metadata,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{duplicate: true, usage_event: UsageEvent}
     */
    private function existingEventResult(UsageEvent $usageEvent, string $payloadHash): array
    {
        if (! hash_equals($usageEvent->payload_hash, $payloadHash)) {
            throw new ConflictHttpException('Idempotency key was reused with a different payload.');
        }

        return [
            'duplicate' => true,
            'usage_event' => $usageEvent,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
