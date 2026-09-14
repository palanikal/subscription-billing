<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Dashboard\BuildMerchantDashboardAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveMerchantApiKey;
use App\Http\Resources\Api\V1\MerchantDashboardResource;
use App\Models\Merchant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MerchantDashboardController extends Controller
{
    public function show(
        Request $request,
        Merchant $merchant,
        BuildMerchantDashboardAction $buildMerchantDashboard,
    ): JsonResponse {
        $authenticatedMerchant = $request->attributes->get(ResolveMerchantApiKey::MERCHANT_ATTRIBUTE);

        if (! $authenticatedMerchant instanceof Merchant) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if ($merchant->id !== $authenticatedMerchant->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return MerchantDashboardResource::make(
            $buildMerchantDashboard->handle($merchant, CarbonImmutable::now('UTC')),
        )->response();
    }
}
