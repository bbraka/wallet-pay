<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\Merchant\AuthService;
use App\Http\Requests\Merchant\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Merchant Authentication",
 *     description="Authentication endpoints for merchant area"
 * )
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/merchant/login",
     *     operationId="merchantLogin",
     *     tags={"Merchant Authentication"},
     *     summary="Login to merchant area",
     *     description="Authenticate user and create session for merchant/admin access",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            \Log::info('Login request data', $request->all());
            $result = $this->authService->login($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'user' => $result['user'],
                'token' => $result['token']
            ]);
        } catch (ValidationException $e) {
            \Log::error('Login validation failed', ['errors' => $e->errors(), 'input' => $request->all()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/user",
     *     operationId="getMerchantUser",
     *     tags={"Merchant Authentication"},
     *     summary="Get authenticated user information",
     *     description="Retrieve current user data for profile menu and navigation",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/UserResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function user(): JsonResponse
    {
        try {
            $result = $this->authService->getUserInfo();
            
            $user = $result['user'];
            $userData = $user->toArray();
            $userData['roles'] = $user->roles->toArray();
            
            return response()->json([
                'success' => true,
                'user' => $userData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant/logout",
     *     operationId="merchantLogout",
     *     tags={"Merchant Authentication"},
     *     summary="Logout from merchant area",
     *     description="Destroy user session",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(ref="#/components/schemas/LogoutResponse")
     *     )
     * )
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/users",
     *     operationId="getMerchantUsers",
     *     tags={"Merchant Authentication"},
     *     summary="Get list of users",
     *     description="Retrieve list of all users with their email and basic information",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/UsersResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function users(): JsonResponse
    {
        try {
            $result = $this->authService->getUserList();
            
            return response()->json([
                'success' => true,
                'users' => $result['users']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}