<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="User Wallet API",
 *     version="1.0.0",
 *     description="API for User Wallet application with order and transaction management",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     )
 * )
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 */
class SchemaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/schema",
     *     tags={"Schema"},
     *     summary="Get OpenAPI schema",
     *     description="Returns the complete OpenAPI 3.0 schema for the application",
     *     @OA\Response(
     *         response=200,
     *         description="OpenAPI schema",
     *         @OA\JsonContent(
     *             type="object",
     *             description="OpenAPI 3.0 specification"
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $swaggerJsonPath = storage_path('api-docs/api-docs.json');
        
        if (file_exists($swaggerJsonPath)) {
            $swagger = json_decode(file_get_contents($swaggerJsonPath), true);
            return response()->json($swagger);
        }

        // Fallback to generating on-the-fly
        $swagger = \OpenApi\Generator::scan([
            app_path('Models'),
            app_path('Http/Controllers'),
        ]);

        return response()->json($swagger->toArray());
    }
}