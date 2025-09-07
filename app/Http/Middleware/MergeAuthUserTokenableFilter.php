<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MergeAuthUserTokenableFilter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $filters = $request->input('filter', []);
        $updated_filters = array_merge($filters, [
            'tokenable_id' => auth()->id(),
            'tokenable_type' => is_object($user = auth()->user()) ? get_class($user) : $user,
        ]);
        $request->merge(['filter' => $updated_filters]);

        return $next($request);
    }
}
