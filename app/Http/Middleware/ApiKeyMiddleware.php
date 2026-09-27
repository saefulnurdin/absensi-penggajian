<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-KEY') ?? $request->query('api_key');

        // optional_device_key digunakan bila device mengirim key perangkat.
        $optionalKey = $request->header('X-DEVICE-KEY');

        if (! hash_equals((string) config('app.api_key'), (string) $key)) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak valid.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
