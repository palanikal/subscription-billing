<?php

namespace App\Providers;

use App\Actions\Billing\PlanPricingCache;
use App\Http\Middleware\ResolveMerchantApiKey;
use App\Models\ApiKey;
use App\Models\Plan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('usage-ingestion', function (Request $request): Limit {
            $apiKey = $request->attributes->get(ResolveMerchantApiKey::API_KEY_ATTRIBUTE);

            $limiterKey = $apiKey instanceof ApiKey
                ? "api-key:{$apiKey->id}"
                : "ip:{$request->ip()}";

            return Limit::perMinute(120)->by($limiterKey);
        });

        Plan::updated(function (Plan $plan): void {
            app(PlanPricingCache::class)->forget($plan);
        });

        Plan::deleted(function (Plan $plan): void {
            app(PlanPricingCache::class)->forget($plan);
        });
    }
}
