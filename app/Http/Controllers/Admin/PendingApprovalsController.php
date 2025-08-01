<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PendingApprovalsController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Display a listing of pending withdrawals and payments.
     */
    public function index(): View
    {
        $pendingWithdrawals = Order::where('status', OrderStatus::PENDING_APPROVAL)
            ->whereIn('order_type', [OrderType::USER_WITHDRAWAL, OrderType::ADMIN_WITHDRAWAL])
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $pendingPayments = Order::where('status', OrderStatus::PENDING_PAYMENT)
            ->with(['user', 'receiver', 'topUpProvider'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.pending-approvals.index', compact('pendingWithdrawals', 'pendingPayments'));
    }

    /**
     * Approve a single withdrawal
     */
    public function approveWithdrawal(Order $order): RedirectResponse
    {
        try {
            if (!$order->order_type->isWithdrawal()) {
                return back()->with('error', 'Order is not a withdrawal order.');
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                return back()->with('error', 'Order is not pending approval.');
            }

            $this->orderService->approveWithdrawal($order);

            return back()->with('success', "Withdrawal #{$order->id} approved successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to approve withdrawal: {$e->getMessage()}");
        }
    }

    /**
     * Deny a single withdrawal
     */
    public function denyWithdrawal(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'denial_reason' => 'nullable|string|max:500'
        ]);

        try {
            if (!$order->order_type->isWithdrawal()) {
                return back()->with('error', 'Order is not a withdrawal order.');
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                return back()->with('error', 'Order is not pending approval.');
            }

            $this->orderService->denyWithdrawal($order, $request->input('denial_reason'));

            return back()->with('success', "Withdrawal #{$order->id} denied successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to deny withdrawal: {$e->getMessage()}");
        }
    }

    /**
     * Bulk approve withdrawals
     */
    public function bulkApproveWithdrawals(Request $request): RedirectResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id'
        ]);

        $orderIds = $request->input('order_ids');
        $successCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                
                if ($order->order_type->isWithdrawal() && $order->status === OrderStatus::PENDING_APPROVAL) {
                    $this->orderService->approveWithdrawal($order);
                    $successCount++;
                } else {
                    $errors[] = "Order #{$orderId} is not eligible for approval.";
                }
            } catch (\Exception $e) {
                $errors[] = "Order #{$orderId}: {$e->getMessage()}";
            }
        }

        $message = "Successfully approved {$successCount} withdrawal(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    /**
     * Bulk deny withdrawals
     */
    public function bulkDenyWithdrawals(Request $request): RedirectResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id',
            'bulk_denial_reason' => 'nullable|string|max:500'
        ]);

        $orderIds = $request->input('order_ids');
        $denialReason = $request->input('bulk_denial_reason');
        $successCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                
                if ($order->order_type->isWithdrawal() && $order->status === OrderStatus::PENDING_APPROVAL) {
                    $this->orderService->denyWithdrawal($order, $denialReason);
                    $successCount++;
                } else {
                    $errors[] = "Order #{$orderId} is not eligible for denial.";
                }
            } catch (\Exception $e) {
                $errors[] = "Order #{$orderId}: {$e->getMessage()}";
            }
        }

        $message = "Successfully denied {$successCount} withdrawal(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    /**
     * Approve a single payment
     */
    public function approvePayment(Order $order): RedirectResponse
    {
        try {
            if ($order->status !== OrderStatus::PENDING_PAYMENT) {
                return back()->with('error', 'Order is not pending payment.');
            }

            $this->orderService->confirmPayment($order, backpack_user());

            return back()->with('success', "Payment #{$order->id} approved successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to approve payment: {$e->getMessage()}");
        }
    }

    /**
     * Reject a single payment
     */
    public function rejectPayment(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        try {
            if ($order->status !== OrderStatus::PENDING_PAYMENT) {
                return back()->with('error', 'Order is not pending payment.');
            }

            $this->orderService->rejectPayment($order, backpack_user());

            return back()->with('success', "Payment #{$order->id} rejected successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to reject payment: {$e->getMessage()}");
        }
    }

    /**
     * Bulk approve payments
     */
    public function bulkApprovePayments(Request $request): RedirectResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id'
        ]);

        $orderIds = $request->input('order_ids');
        $successCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                
                if ($order->status === OrderStatus::PENDING_PAYMENT) {
                    $this->orderService->confirmPayment($order, backpack_user());
                    $successCount++;
                } else {
                    $errors[] = "Order #{$orderId} is not pending payment.";
                }
            } catch (\Exception $e) {
                $errors[] = "Order #{$orderId}: {$e->getMessage()}";
            }
        }

        $message = "Successfully approved {$successCount} payment(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    /**
     * Bulk reject payments
     */
    public function bulkRejectPayments(Request $request): RedirectResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id',
            'bulk_rejection_reason' => 'nullable|string|max:500'
        ]);

        $orderIds = $request->input('order_ids');
        $successCount = 0;
        $errors = [];

        foreach ($orderIds as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                
                if ($order->status === OrderStatus::PENDING_PAYMENT) {
                    $this->orderService->rejectPayment($order, backpack_user());
                    $successCount++;
                } else {
                    $errors[] = "Order #{$orderId} is not pending payment.";
                }
            } catch (\Exception $e) {
                $errors[] = "Order #{$orderId}: {$e->getMessage()}";
            }
        }

        $message = "Successfully rejected {$successCount} payment(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    /**
     * Get pending approvals data as JSON (for AJAX requests)
     */
    public function getPendingData(): JsonResponse
    {
        $pendingWithdrawals = Order::where('status', OrderStatus::PENDING_APPROVAL)
            ->whereIn('order_type', [OrderType::USER_WITHDRAWAL, OrderType::ADMIN_WITHDRAWAL])
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingPayments = Order::where('status', OrderStatus::PENDING_PAYMENT)
            ->with(['user', 'receiver', 'topUpProvider'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_withdrawals' => $pendingWithdrawals,
            'pending_payments' => $pendingPayments,
            'counts' => [
                'withdrawals' => $pendingWithdrawals->count(),
                'payments' => $pendingPayments->count(),
            ]
        ]);
    }
}