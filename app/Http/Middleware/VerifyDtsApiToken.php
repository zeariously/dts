<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDtsApiToken
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $configuredToken = trim(
            (string) config('dts_api.token', '')
        );

        if ($configuredToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'DTS API is not configured.',
            ], 503);
        }

        $providedToken = trim(
            (string) (
                $request->bearerToken()
                ?: $request->header('X-DTS-API-Key', '')
            )
        );

        if (
            $providedToken === ''
            || ! hash_equals(
                $configuredToken,
                $providedToken
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
