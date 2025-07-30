<?php

use App\Http\Controllers\SchemaController;
use App\Http\Controllers\Merchant\AuthController;
use App\Http\Controllers\Merchant\OrdersController;
use App\Http\Controllers\Merchant\TopUpProvidersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// OpenAPI Schema endpoint
Route::get('/schema', [SchemaController::class, 'index'])->name('api.schema');

// Merchant Authentication Routes
Route::prefix('merchant')->name('merchant.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    
    Route::middleware(\App\Http\Middleware\CustomAuth::class)->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::get('/users', [AuthController::class, 'users'])->name('users');
        
        // Orders CRUD
        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrdersController::class, 'store'])->name('orders.store');
        Route::post('/orders/withdrawal', [OrdersController::class, 'withdrawal'])->name('orders.withdrawal');
        Route::get('/orders/rules', [OrdersController::class, 'rules'])->name('orders.rules');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show')->where('order', '[0-9]+');
        Route::put('/orders/{order}', [OrdersController::class, 'update'])->name('orders.update')->where('order', '[0-9]+');
        Route::delete('/orders/{order}', [OrdersController::class, 'destroy'])->name('orders.destroy')->where('order', '[0-9]+');
        
        // Top-up providers
        Route::get('/top-up-providers', [TopUpProvidersController::class, 'index'])->name('top-up-providers.index');
    });
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});