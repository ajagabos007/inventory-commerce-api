<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $user = auth()->user();

        app()->instance('currentStoreId', $user?->staff?->store_id);

        if ($user && $user->can('switch', Store::class)) {
            app()->instance('currentStoreId', $request->header('x-store'));
        }

        return $next($request);
    }
}
