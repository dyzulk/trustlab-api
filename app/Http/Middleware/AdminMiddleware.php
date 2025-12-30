<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isAdminOrOwner()) {
            $role = $request->user() ? $request->user()->role : 'guest';
            return response()->json([
                'message' => "Unauthorized. Admin access required. (Current role: {$role})"
            ], 403);
        }

        return $next($request);
    }
}
