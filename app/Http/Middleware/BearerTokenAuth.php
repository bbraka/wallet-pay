<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class BearerTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        \Log::info('BearerTokenAuth middleware', [
            'has_token' => $token ? 'yes' : 'no',
            'token_preview' => $token ? substr($token, 0, 10) . '...' : null
        ]);
        
        if ($token) {
            // Hash the token to match what's stored in the database
            $hashedToken = hash('sha256', $token);
            
            // Find user with matching token
            $user = User::where('api_token', $hashedToken)->first();
            
            \Log::info('Token validation', [
                'user_found' => $user ? 'yes' : 'no',
                'user_id' => $user?->id,
                'hash_preview' => substr($hashedToken, 0, 10) . '...'
            ]);
            
            if ($user) {
                // Set the user for this request in multiple guards to ensure it works
                Auth::setUser($user);
                Auth::guard('api')->setUser($user);
                
                // Also set in the request so it persists
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });
                
                \Log::info('User authenticated via Bearer token', ['user_id' => $user->id]);
                return $next($request);
            } else {
                \Log::info('Invalid Bearer token provided');
                // Invalid token provided
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token.'
                ], 401);
            }
        }
        
        \Log::info('No Bearer token provided, continuing without auth');
        // No token provided - let the request continue (for actingAs tests)
        // The AuthService will handle the authentication check
        return $next($request);
    }
}