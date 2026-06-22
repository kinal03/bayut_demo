<?php

namespace Modules\UserManagement\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\UserManagement\App\Models\TenantPersonalAccessToken;
use Modules\UserManagement\App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class AuthenticateSanctumMultiTenant
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // First try to find token in central database
        tenancy()->end();

        $token = PersonalAccessToken::findToken($bearer);

        if ($token && $token->tokenable) {
            if ($token->token_type === 'access') {
                if ($token->expires_at && now()->greaterThan($token->expires_at)) {
                    $token->delete(); // expired token delete

                    return response()->json([
                        'message' => 'Access token expired'
                    ], 401);
                }
            }

            // User exists in central database
            $user = $token->tokenable;

            $request->setUserResolver(fn () => $user);
            // Ensure Auth uses the sanctum guard and has the resolved user so Spatie\Permission works
            Auth::shouldUse('sanctum');
            Auth::guard('sanctum')->setUser($user);

            return $next($request);
        }

        /*
        |----------------------------------------------------------------------
        | TENANT TOKEN LOOKUP
        | Optimization: Use X-Tenant-ID header to directly target tenant DB.
        | Falls back to scanning all tenants if header not provided.
        |----------------------------------------------------------------------
        */
        $tenantIdHeader = $request->header('X-Tenant-ID');

        if ($tenantIdHeader) {
            // ✅ Fast path: directly look in the specified tenant DB (O(1))
            $tenant = Tenant::find($tenantIdHeader);

            if ($tenant) {
                try {
                    $conn  = getTenantConnection($tenant);
                    $token = TenantPersonalAccessToken::findTokenOnConnection($bearer, $conn);

                    if ($token && $token->tokenable) {
                        // ✅ FIX: Check token_type for tenant tokens too (refresh token cannot be used as auth)
                        if ($token->token_type !== 'access') {
                            return response()->json(['message' => 'Unauthenticated'], 401);
                        }

                        if ($token->expires_at && now()->greaterThan($token->expires_at)) {
                            $token->delete();
                            tenancy()->end();
                            return response()->json(['message' => 'Access token expired'], 401);
                        }

                        $user = $token->tokenable;
                        $request->setUserResolver(fn () => $user);
                        Auth::shouldUse('sanctum');
                        Auth::guard('sanctum')->setUser($user);

                        return $next($request);
                    }
                } catch (\Throwable $e) {
                    // fall through to full scan below
                }
            }
        }

        // Fallback: scan all tenants (O(n)) — used when no X-Tenant-ID header
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            try {
                $conn = getTenantConnection($tenant);
                $token = TenantPersonalAccessToken::findTokenOnConnection(
                    $bearer,
                    $conn
                );

                if ($token && $token->tokenable) {
                    // ✅ FIX: Check token_type for tenant tokens too (refresh token cannot be used as auth)
                    if ($token->token_type !== 'access') {
                        continue; // skip refresh tokens, keep searching
                    }

                    if (
                        $token->expires_at &&
                        now()->greaterThan($token->expires_at)
                    ) {
                        $token->delete();
                        tenancy()->end();

                        return response()->json([
                            'message' => 'Access token expired'
                        ], 401);
                    }

                    $user = $token->tokenable;
                    $request->setUserResolver(fn () => $user);

                    Auth::shouldUse('sanctum');
                    Auth::guard('sanctum')->setUser($user);

                    return $next($request);
                }
            } catch (\Throwable $e) {
                // Skip invalid tenant connection
                continue;
            }
        }

        // End tenancy if no token found
        tenancy()->end();

        // return $next($request);
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
