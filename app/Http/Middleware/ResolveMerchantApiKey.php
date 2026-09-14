<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMerchantApiKey
{
    public const API_KEY_ATTRIBUTE = 'apiKey';

    public const MERCHANT_ATTRIBUTE = 'merchant';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextKey = $request->header('X-API-Key');

        if (! is_string($plainTextKey) || trim($plainTextKey) === '') {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = ApiKey::query()
            ->with('merchant')
            ->where('key_hash', hash('sha256', trim($plainTextKey)))
            ->whereNull('revoked_at')
            ->first();

        if ($apiKey === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::API_KEY_ATTRIBUTE, $apiKey);
        $request->attributes->set(self::MERCHANT_ATTRIBUTE, $apiKey->merchant);

        return $next($request);
    }
}
