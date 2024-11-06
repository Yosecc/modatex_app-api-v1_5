<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class validaTokenApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Pre-Middleware Action

        $token = $request->header('x-api-token');
        
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $decoded = JWTAuth::setToken($token)->getPayload();
            $response = $next($request);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


        

        // Post-Middleware Action

        return $response;
    }
}
