<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/merchant/transactions",
     *     summary="Get merchant user transactions",
     *     operationId="getMerchantTransactions",
     *     tags={"Merchant Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter transactions created after this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter transactions created before this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by transaction status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by transaction type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"credit", "debit"})
     *     ),
     *     @OA\Parameter(
     *         name="min_amount",
     *         in="query",
     *         description="Filter transactions with amount greater than or equal to this value",
     *         required=false,
     *         @OA\Schema(type="number", format="decimal")
     *     ),
     *     @OA\Parameter(
     *         name="max_amount",
     *         in="query",
     *         description="Filter transactions with amount less than or equal to this value",
     *         required=false,
     *         @OA\Schema(type="number", format="decimal")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of user transactions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Transaction")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $query = Transaction::where('user_id', $user->id)
            ->with(['order', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);  
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/transactions/{transaction}",
     *     summary="Get merchant transaction details",
     *     operationId="getMerchantTransaction",
     *     tags={"Merchant Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="transaction",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - transaction does not belong to user"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $user = auth()->user();
        
        if ($transaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $transaction->load(['order', 'createdBy']);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }
}