<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKey
{
    public function handle(Request $request, Closure $next)
    {
        if (! hash_equals((string) config('hotel.api_key'), (string) $request->header('X-API-Key', ''))) {
            return response()->json(['error' => 'Invalid or missing API key'], 401);
        }
        return $next($request);
    }
}
