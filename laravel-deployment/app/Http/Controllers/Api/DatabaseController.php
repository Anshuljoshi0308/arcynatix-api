<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class DatabaseController extends Controller
{
    /**
     * Check database connectivity
     */
    public function checkConnection()
    {
        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Database connected successfully',
                'database' => $dbName,
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get database information
     */
    public function getDatabaseInfo()
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $dbName = DB::connection()->getDatabaseName();
            
            // Get table names
            $tableNames = [];
            $tablesKey = 'Tables_in_' . $dbName;
            foreach ($tables as $table) {
                $tableNames[] = $table->$tablesKey;
            }

            return response()->json([
                'status' => 'success',
                'database_name' => $dbName,
                'total_tables' => count($tableNames),
                'tables' => $tableNames,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get database info',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Create a test table
     */
    public function createTestTable()
    {
        try {
            // Drop table if exists
            Schema::dropIfExists('api_test_users');
            
            // Create test table
            Schema::create('api_test_users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Test table "api_test_users" created successfully',
                'table_name' => 'api_test_users',
                'timestamp' => now()
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create test table',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Insert test data (CREATE)
     */
    public function insertTestData(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:api_test_users,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'status' => 'nullable|in:active,inactive'
            ]);

            $data['status'] = $data['status'] ?? 'active';
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $id = DB::table('api_test_users')->insertGetId($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Data inserted successfully',
                'data' => array_merge($data, ['id' => $id]),
                'timestamp' => now()
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to insert data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get all test data (READ)
     */
    public function getAllTestData()
    {
        try {
            $data = DB::table('api_test_users')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Data retrieved successfully',
                'count' => $data->count(),
                'data' => $data,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get single test data by ID (READ)
     */
    public function getTestDataById($id)
    {
        try {
            $data = DB::table('api_test_users')->where('id', $id)->first();

            if (!$data) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data not found',
                    'timestamp' => now()
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data retrieved successfully',
                'data' => $data,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Update test data (UPDATE)
     */
    public function updateTestData(Request $request, $id)
    {
        try {
            // Check if record exists
            $exists = DB::table('api_test_users')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data not found',
                    'timestamp' => now()
                ], 404);
            }

            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:api_test_users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive'
            ]);

            $data['updated_at'] = now();

            DB::table('api_test_users')->where('id', $id)->update($data);

            $updatedData = DB::table('api_test_users')->where('id', $id)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Data updated successfully',
                'data' => $updatedData,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Delete test data (DELETE)
     */
    public function deleteTestData($id)
    {
        try {
            // Check if record exists
            $data = DB::table('api_test_users')->where('id', $id)->first();
            if (!$data) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data not found',
                    'timestamp' => now()
                ], 404);
            }

            DB::table('api_test_users')->where('id', $id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data deleted successfully',
                'deleted_data' => $data,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Delete all test data
     */
    public function clearAllTestData()
    {
        try {
            $count = DB::table('api_test_users')->count();
            DB::table('api_test_users')->truncate();

            return response()->json([
                'status' => 'success',
                'message' => 'All test data cleared successfully',
                'deleted_count' => $count,
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear test data',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Run database health check
     */
    public function healthCheck()
    {
        try {
            $startTime = microtime(true);
            
            // Test connection
            DB::connection()->getPdo();
            
            // Test query
            $result = DB::select('SELECT 1 as test');
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return response()->json([
                'status' => 'healthy',
                'message' => 'Database is healthy and responsive',
                'response_time_ms' => $responseTime,
                'database' => DB::connection()->getDatabaseName(),
                'host' => config('database.connections.mysql.host'),
                'query_test' => $result[0]->test === 1 ? 'passed' : 'failed',
                'timestamp' => now()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'Database health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 503);
        }
    }
}