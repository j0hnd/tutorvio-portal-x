<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowSwaggerAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $environment = app()->environment();
        $allowedEnvironments = config('l5-swagger.allowed_environments', ['local', 'testing']);
        $productionEnabled = (bool) config('l5-swagger.allow_production', false);

        if (
            in_array($environment, $allowedEnvironments, true)
            || ($environment === 'production' && $productionEnabled)
        ) {
            return $next($request);
        }

        abort(404);
    }
}
