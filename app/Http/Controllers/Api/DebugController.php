<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    /**
     * Debug endpoint to check if Laravel is working
     */
    public function test(Request $request)
    {
        // Set CORS headers immediately for debugging
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With, Origin');
        
        return response()->json([
            'success' => true,
            'message' => 'Laravel is working correctly!',
            'timestamp' => now(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'environment' => app()->environment(),
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization, X-Requested-With, Origin',
            'Content-Type' => 'application/json',
        ]);
    }
    
    /**
     * Handle OPTIONS request for debug
     */
    public function options()
    {
        return response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization, X-Requested-With, Origin',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}