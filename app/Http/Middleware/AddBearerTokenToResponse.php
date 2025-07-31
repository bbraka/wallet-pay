<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AddBearerTokenToResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add token for authenticated users on API routes
        if ($request->is('api/merchant/*') && Auth::check()) {
            $user = Auth::user();
            
            // Get or create a fresh token for the user
            $user->tokens()->delete(); // Delete old tokens
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Add token to response headers
            $response->headers->set('Authorization', 'Bearer ' . $token);
            $response->headers->set('X-Auth-Token', $token);
        }

        return $response;
    }
}
