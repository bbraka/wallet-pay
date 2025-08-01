<?php

use App\Http\Controllers\SchemaController;
use App\Http\Controllers\Merchant\AuthController;
use App\Http\Controllers\Merchant\OrdersController;
use App\Http\Controllers\Merchant\TopUpProvidersController;
use App\Http\Controllers\Merchant\TransactionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// OpenAPI Schema endpoint
Route::get('/schema', [SchemaController::class, 'index'])->name('api.schema');

// Merchant Authentication Routes - using Bearer token authentication
Route::prefix('merchant')->name('merchant.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    
    Route::middleware('auth:api')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::get('/users', [AuthController::class, 'users'])->name('users');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        
        // Orders CRUD
        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/pending-transfers', [OrdersController::class, 'pendingTransfers'])->name('orders.pendingTransfers');
        Route::post('/orders', [OrdersController::class, 'store'])->name('orders.store');
        Route::post('/orders/withdrawal', [OrdersController::class, 'withdrawal'])->name('orders.withdrawal');
        Route::get('/orders/rules', [OrdersController::class, 'rules'])->name('orders.rules');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show')->where('order', '[0-9]+');
        Route::put('/orders/{order}', [OrdersController::class, 'update'])->name('orders.update')->where('order', '[0-9]+');
        Route::delete('/orders/{order}', [OrdersController::class, 'destroy'])->name('orders.destroy')->where('order', '[0-9]+');
        Route::post('/orders/{order}/confirm', [OrdersController::class, 'confirm'])->name('orders.confirm')->where('order', '[0-9]+');
        Route::post('/orders/{order}/reject', [OrdersController::class, 'reject'])->name('orders.reject')->where('order', '[0-9]+');
        
        // Top-up providers
        Route::get('/top-up-providers', [TopUpProvidersController::class, 'index'])->name('top-up-providers.index');
        
        // Transactions
        Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionsController::class, 'show'])->name('transactions.show')->where('transaction', '[0-9]+');
    });
});