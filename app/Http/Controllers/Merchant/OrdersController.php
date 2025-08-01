<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\CreateOrderRequest;
use App\Http\Requests\Merchant\CreateWithdrawalRequest;
use App\Http\Requests\Merchant\UpdateOrderRequest;
use App\Http\Requests\Merchant\OrderIndexRequest;
use App\Models\Order;
use App\Services\Merchant\OrdersService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Order management endpoints"
 * )
 */
class OrdersController extends Controller
{
    use AuthorizesRequests;
    
    protected OrdersService $ordersService;

    public function __construct(OrdersService $ordersService)
    {
        $this->ordersService = $ordersService;
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/orders/pending-transfers",
     *     tags={"Orders"},
     *     summary="Get pending transfers received by the user",
     *     operationId="getMerchantPendingTransfers",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of pending transfers received",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Order")
     *         )
     *     )
     * )
     */
    public function pendingTransfers(): JsonResponse
    {
        $user = request()->user();
        $pendingTransfers = $this->ordersService->getPendingTransfersReceived($user);
        
        return response()->json($pendingTransfers);
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/orders",
     *     tags={"Orders"},
     *     summary="List user's orders with optional filtering",
     *     operationId="getMerchantOrders",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter orders from this date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter orders to this date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="min_amount",
     *         in="query",
     *         description="Minimum order amount",
     *         @OA\Schema(type="number", format="decimal")
     *     ),
     *     @OA\Parameter(
     *         name="max_amount",
     *         in="query",
     *         description="Maximum order amount",
     *         @OA\Schema(type="number", format="decimal")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         @OA\Schema(type="string", enum={"pending_payment", "pending_approval", "completed", "cancelled", "refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="receiver_user_id",
     *         in="query",
     *         description="Filter by receiver user ID (for transfers)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of orders",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();
        
        $orders = $this->ordersService->getOrdersWithFilters($user, $filters);
        
        return response()->json($orders);
    }

    /**
     * @OA\Post(
     *     path="/api/merchant/orders",
     *     tags={"Orders"},
     *     summary="Create a new order",
     *     operationId="createMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "amount"},
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="amount", type="number", format="decimal"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="receiver_user_id", type="integer", description="Required for transfers"),
     *             @OA\Property(property="top_up_provider_id", type="integer", description="Required for top-ups"),
     *             @OA\Property(property="provider_reference", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $order = $this->ordersService->createOrder($user, $data);
        
        return response()->json($order, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/orders/rules",
     *     tags={"Orders"},
     *     summary="Get order validation rules and limits",
     *     operationId="getMerchantOrderRules",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Order validation rules",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="max_top_up_amount", type="number"),
     *             @OA\Property(property="max_transfer_amount", type="number"),
     *             @OA\Property(property="required_fields", type="object"),
     *             @OA\Property(property="allowed_statuses", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function rules(): JsonResponse
    {
        $rules = $this->ordersService->getValidationRules();
        
        return response()->json($rules);
    }

    /**
     * @OA\Get(
     *     path="/api/merchant/orders/{order}",
     *     tags={"Orders"},
     *     summary="Get a specific order",
     *     operationId="getMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     )
     * )
     */
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        
        return response()->json($order->load(['receiver', 'topUpProvider']));
    }

    /**
     * @OA\Put(
     *     path="/api/merchant/orders/{order}",
     *     tags={"Orders"},
     *     summary="Update an existing order",
     *     operationId="updateMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="amount", type="number", format="decimal"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="provider_reference", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or order cannot be updated"
     *     )
     * )
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);
        
        $data = $request->validated();
        $updatedOrder = $this->ordersService->updateOrder($order, $data);
        
        return response()->json($updatedOrder);
    }

    /**
     * @OA\Delete(
     *     path="/api/merchant/orders/{order}",
     *     tags={"Orders"},
     *     summary="Cancel an order",
     *     operationId="cancelMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Order cannot be cancelled"
     *     )
     * )
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);
        
        $cancelledOrder = $this->ordersService->cancelOrder($order);
        
        return response()->json($cancelledOrder);
    }

    /**
     * @OA\Post(
     *     path="/api/merchant/orders/withdrawal",
     *     tags={"Orders"},
     *     summary="Create a withdrawal request",
     *     operationId="createMerchantWithdrawal",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="decimal"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Withdrawal request created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or insufficient balance"
     *     )
     * )
     */
    public function withdrawal(CreateWithdrawalRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        
        $order = $this->ordersService->createWithdrawalRequest($user, $data);
        
        return response()->json($order, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/merchant/orders/{order}/confirm",
     *     tags={"Orders"},
     *     summary="Confirm a pending order",
     *     operationId="confirmMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order confirmed successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Order cannot be confirmed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to confirm this order"
     *     )
     * )
     */
    public function confirm(Order $order): JsonResponse
    {
        $this->authorize('confirm', $order);
        
        $confirmedOrder = $this->ordersService->confirmOrder($order);
        
        return response()->json($confirmedOrder);
    }

    /**
     * @OA\Post(
     *     path="/api/merchant/orders/{order}/reject",
     *     tags={"Orders"},
     *     summary="Reject a pending order",
     *     operationId="rejectMerchantOrder",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order rejected successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Order cannot be rejected"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to reject this order"
     *     )
     * )
     */
    public function reject(Order $order): JsonResponse
    {
        $this->authorize('reject', $order);
        
        $rejectedOrder = $this->ordersService->rejectOrder($order);
        
        return response()->json($rejectedOrder);
    }
}