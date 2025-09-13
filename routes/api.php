<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\ContactController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Contact Management API Routes
Route::prefix('contacts')->group(function () {
    
    // Public endpoints (no authentication required)
    Route::post('/', [ContactController::class, 'store']); // Create contact form submission
    Route::get('/track/{contact_id}', [ContactController::class, 'track']); // Customer tracking portal
    
    // Admin endpoints (should be protected with authentication middleware in production)
    Route::get('/', [ContactController::class, 'index']); // List all contacts with filters
    Route::get('/stats', [ContactController::class, 'stats']); // Dashboard statistics
    Route::get('/overdue', [ContactController::class, 'overdue']); // Get overdue contacts
    Route::get('/{identifier}', [ContactController::class, 'show']); // Show contact by ID or contact_id
    Route::put('/{id}', [ContactController::class, 'update']); // Update contact
    Route::patch('/{id}', [ContactController::class, 'update']); // Partial update contact
    Route::delete('/{id}', [ContactController::class, 'destroy']); // Delete contact
    Route::post('/{id}/assign', [ContactController::class, 'assign']); // Assign contact to user
    
    // Additional admin endpoints
    Route::get('/priority/{priority}', function($priority) {
        return app(ContactController::class)->index(request()->merge(['priority' => $priority]));
    }); // Get contacts by priority
    
    Route::get('/status/{status}', function($status) {
        return app(ContactController::class)->index(request()->merge(['status' => $status]));
    }); // Get contacts by status
    
    Route::get('/user/{userId}', function($userId) {
        return app(ContactController::class)->index(request()->merge(['handled_by' => $userId]));
    }); // Get contacts by assigned user
});

// Database connectivity and CRUD API routes
Route::prefix('database')->group(function () {
    
    // Database connectivity routes
    Route::get('/check-connection', [DatabaseController::class, 'checkConnection']);
    Route::get('/info', [DatabaseController::class, 'getDatabaseInfo']);
    Route::get('/health', [DatabaseController::class, 'healthCheck']);
    
    // Test table management
    Route::post('/create-test-table', [DatabaseController::class, 'createTestTable']);
    Route::delete('/clear-all-data', [DatabaseController::class, 'clearAllTestData']);
    
    // CRUD operations for test data
    Route::post('/test-data', [DatabaseController::class, 'insertTestData']);
    Route::get('/test-data', [DatabaseController::class, 'getAllTestData']);
    Route::get('/test-data/{id}', [DatabaseController::class, 'getTestDataById']);
    Route::put('/test-data/{id}', [DatabaseController::class, 'updateTestData']);
    Route::patch('/test-data/{id}', [DatabaseController::class, 'updateTestData']);
    Route::delete('/test-data/{id}', [DatabaseController::class, 'deleteTestData']);
});

// Additional utility routes
Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working',
        'timestamp' => now(),
        'app_name' => config('app.name'),
        'app_env' => config('app.env')
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'services' => [
            'database' => 'connected',
            'api' => 'operational'
        ]
    ]);
});

// API Documentation helper
Route::get('/endpoints', function () {
    return response()->json([
        'contact_management' => [
            'POST /api/contacts' => 'Create new contact form submission',
            'GET /api/contacts' => 'List contacts with filters (admin)',
            'GET /api/contacts/{id}' => 'Show specific contact (admin)',
            'PUT /api/contacts/{id}' => 'Update contact (admin)',
            'DELETE /api/contacts/{id}' => 'Delete contact (admin)',
            'POST /api/contacts/{id}/assign' => 'Assign contact to user (admin)',
            'GET /api/contacts/stats' => 'Dashboard statistics (admin)',
            'GET /api/contacts/overdue' => 'Get overdue contacts (admin)',
            'GET /api/contacts/track/{contact_id}' => 'Customer tracking portal (public)',
        ],
        'database_testing' => [
            'GET /api/database/check-connection' => 'Test database connection',
            'GET /api/database/info' => 'Get database information',
            'GET /api/database/health' => 'Database health check',
            'POST /api/database/create-test-table' => 'Create test table',
            'POST /api/database/test-data' => 'Insert test data',
            'GET /api/database/test-data' => 'Get all test data',
            'GET /api/database/test-data/{id}' => 'Get test data by ID',
            'PUT /api/database/test-data/{id}' => 'Update test data',
            'DELETE /api/database/test-data/{id}' => 'Delete test data',
        ],
        'utility' => [
            'GET /api/ping' => 'API status check',
            'GET /api/health' => 'System health check',
            'GET /api/endpoints' => 'This endpoint documentation'
        ]
    ]);
});