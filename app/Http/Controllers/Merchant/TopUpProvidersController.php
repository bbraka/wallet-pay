<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\TopUpProvider;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Top-up Providers",
 *     description="Top-up provider endpoints"
 * )
 */
class TopUpProvidersController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/merchant/top-up-providers",
     *     tags={"Top-up Providers"},
     *     summary="List active top-up providers",
     *     operationId="getMerchantTopUpProviders",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of active top-up providers",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/TopUpProvider")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $providers = TopUpProvider::active()
            ->select(['id', 'name', 'code', 'description', 'requires_reference'])
            ->orderBy('name')
            ->get();
        
        return response()->json($providers);
    }
}