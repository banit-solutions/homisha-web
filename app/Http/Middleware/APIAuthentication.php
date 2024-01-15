<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class APIAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authKey = $request->header('Authorization');

        $keyExists = DB::table('users')->where('remember_token', $authKey)->exists();

        if (!$keyExists) {
            $isValid = $this->authenticateKey($authKey);

            if (!$isValid) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access.'], 401); // Unauthorized
            }
        }

        return $next($request);
    }

    function authenticateKey($key)
    {
        // Check if the key is alphanumeric
        if (!ctype_alnum($key)) {
            return false; // Key is not alphanumeric
        }

        // Calculate the sum of ASCII values of the characters
        $asciiSum = 0;
        for ($i = 0; $i < strlen($key); $i++) {
            $asciiSum += ord($key[$i]);
        }

        // Check if the sum of ASCII values exceeds 1000
        if ($asciiSum > 1000) {
            return false; // ASCII sum exceeds 1000
        }

        // If all checks pass, return true
        return true;
    }
}