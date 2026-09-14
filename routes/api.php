<?php

use App\Http\Controllers\Api\V1\MerchantDashboardController;
use App\Http\Controllers\Api\V1\SubscriptionPlanChangeController;
use App\Http\Controllers\Api\V1\UsageController;
use Illuminate\Support\Facades\Route;

Route::post('v1/usage', [UsageController::class, 'store'])
    ->middleware(['merchant-api-key', 'throttle:usage-ingestion']);

Route::post('v1/subscriptions/{subscription}/plan-changes', [SubscriptionPlanChangeController::class, 'store'])
    ->middleware('merchant-api-key');

Route::get('v1/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show'])
    ->middleware('merchant-api-key');
