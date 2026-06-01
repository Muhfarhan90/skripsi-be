<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Activitylog\Facades\CauserResolver;
use Symfony\Component\HttpFoundation\Response;

class SetActivityLogCauser
{
    public function handle(Request $request, Closure $next): Response
    {
        $causer = $request->user();
        $causerName = $causer?->fullname ?? $causer?->name ?? null;

        CauserResolver::setCauser($causer);
        app()->instance('activity-log-causer-name', $causerName);

        try {
            return $next($request);
        } finally {
            CauserResolver::setCauser(null);
            app()->forgetInstance('activity-log-causer-name');
        }
    }
}
