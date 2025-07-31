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
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="web",
 *     type="apiKey",
 *     in="cookie",
 *     name="laravel_session",
 *     description="Laravel session authentication"
 * )
 * 
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", example="merchant@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password123"),
 *     @OA\Property(property="remember", type="boolean", example=false, description="Remember login session")
 * )
 * 
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Successfully logged in."),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="token", type="string", example="1|abcdefghijklmnopqrstuvwxyz", description="Bearer token for API authentication")
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="The provided credentials are incorrect."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="UserResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 * 
 * @OA\Schema(
 *     schema="UsersResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(
 *         property="users",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="LogoutResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Successfully logged out.")
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