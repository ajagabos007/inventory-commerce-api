<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureCartToken
{
    /**
     * Handle an incoming request.
     *
     * If the request does not include an X-Cart-Token header, generate one,
     * attach it to the request, and add it to the response so the frontend can store it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get the token from the incoming header
        $cart_token = $request->header('x-cart-token');

        if (blank($cart_token)) {
            $cart_token = (string) Str::uuid();
            $request->headers->set('x-cart-token', $cart_token);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('x-cart-token', $cart_token);

        return $response;
    }
}
