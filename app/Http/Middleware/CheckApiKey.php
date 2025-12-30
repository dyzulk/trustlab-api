<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for underscore only (preference)
        $headerValue = $request->header('TRUSTLAB_API_KEY');
        $keyString = $headerValue ? trim($headerValue) : null;

        if (!$keyString) {
            return response()->json([
                'success' => false,
                'message' => 'API Key is missing. Please provide it in the TRUSTLAB_API_KEY header.'
            ], 401);
        }

        $apiKey = ApiKey::where('key', $keyString)->first();

        if (!$apiKey || !$apiKey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API Key.'
            ], 401);
        }

        // Update last used timestamp
        $apiKey->update(['last_used_at' => now()]);

        // Optional: Dispatch stats update event if needed in the future
        // \App\Events\DashboardStatsUpdated::dispatch($apiKey->user_id);

        // Put the user in the request context
        $user = $apiKey->user;
        $request->merge(['authenticated_user' => $user]);
        $request->setUserResolver(fn () => $user);
        
        if ($user) {
            Auth::setUser($user);
        }
        
        return $next($request);
    }
}
