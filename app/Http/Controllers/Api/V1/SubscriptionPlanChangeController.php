<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Billing\ChangeSubscriptionPlanAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveMerchantApiKey;
use App\Http\Requests\Api\V1\StoreSubscriptionPlanChangeRequest;
use App\Http\Resources\Api\V1\SubscriptionPeriodResource;
use App\Models\Merchant;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionPlanChangeController extends Controller
{
    public function store(
        StoreSubscriptionPlanChangeRequest $request,
        Subscription $subscription,
        ChangeSubscriptionPlanAction $changeSubscriptionPlan,
    ): JsonResponse {
        $merchant = $request->attributes->get(ResolveMerchantApiKey::MERCHANT_ATTRIBUTE);

        if (! $merchant instanceof Merchant) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $period = $changeSubscriptionPlan->handle(
            $merchant,
            $subscription,
            $request->integer('plan_id'),
            CarbonImmutable::parse($request->string('effective_at')->toString())->utc(),
        );

        return SubscriptionPeriodResource::make($period)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
