<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\PlanPricingCache;
use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlanPricingCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_uses_cached_pricing_and_invalidates_it_when_the_plan_changes(): void
    {
        $plan = Plan::factory()->for(Merchant::factory())->create([
            'base_price_cents' => 2_500,
        ]);
        $cache = app(PlanPricingCache::class);
        $initialPricing = $cache->for($plan);

        Cache::put(
            $cache->key($plan),
            [...$initialPricing, 'base_price_cents' => 999],
            now()->addHour(),
        );

        $this->assertSame(999, $cache->for($plan)['base_price_cents']);

        $plan->update(['base_price_cents' => 4_500]);

        $this->assertFalse(Cache::has($cache->key($plan)));
        $this->assertSame(4_500, $cache->for($plan->fresh())['base_price_cents']);
    }
}
