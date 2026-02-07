<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TechnicalRouteGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->environment('production')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && (int) ($user->role_id ?? 0) === 1) {
            return $next($request);
        }

        $configuredToken = (string) config('services.technical_routes.token', '');
        $providedToken = (string) (
            $request->header('X-Technical-Token')
            ?? $request->query('token')
            ?? $request->input('token')
        );

        if ($configuredToken !== '' && hash_equals($configuredToken, $providedToken)) {
            return $next($request);
        }

        abort(403, 'Rota tecnica bloqueada em producao.');
    }
}
