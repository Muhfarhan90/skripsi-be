<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roleName = strtolower((string) $user?->role?->name);

        if (! $user || $roleName !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
