<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionToken
{
    /**
     * Handle an incoming request.
     *
     * Ensures every request includes a valid X-Session-Token header.
     * If missing or invalid, a new UUID is generated and returned in the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Retrieve from header or query
        $sessionToken = $request->header('X-Session-Token')
            ?? $request->query('session_token');

        // Validate token — must be a proper UUID (v4)
        if (blank($sessionToken) || ! Str::isUuid($sessionToken)) {
            $sessionToken = (string) Str::uuid();
        }

        // Attach token to request for downstream usage
        $request->headers->set('X-Session-Token', $sessionToken);
        $request->merge(['session_token' => $sessionToken]);

        // Continue request processing
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Ensure response always includes the token
        $response->headers->set('X-Session-Token', $sessionToken);

        return $response;
    }
}
