<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = auth()->user();

        // User is not logged in
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // Admin can access everything
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check if user's role is allowed
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        // Merchant-only actions are available only after administrator approval.
        if (in_array('merchant', $roles) && $user->role === 'merchant'
            && $user->merchant_status !== 'approved') {
            return response()->json([
                'message' => 'Your merchant account is not approved',
                'merchant_status' => $user->merchant_status,
                'reason' => $user->merchant_status_reason,
            ], 403);
        }

        // Allow request to continue
        return $next($request);
    }
}
