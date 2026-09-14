<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Usage\RecordUsageAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveMerchantApiKey;
use App\Http\Requests\Api\V1\StoreUsageRequest;
use App\Http\Resources\Api\V1\UsageEventResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UsageController extends Controller
{
    public function store(StoreUsageRequest $request, RecordUsageAction $recordUsage): JsonResponse
    {
        $merchant = $request->attributes->get(ResolveMerchantApiKey::MERCHANT_ATTRIBUTE);

        if (! $merchant instanceof Merchant) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $result = $recordUsage->handle($merchant, $request->validated());

        return UsageEventResource::make($result['usage_event'])
            ->additional(['meta' => ['duplicate' => $result['duplicate']]])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
