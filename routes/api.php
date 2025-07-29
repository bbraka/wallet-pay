<?php

use App\Http\Controllers\SchemaController;
use App\Http\Controllers\Merchant\AuthController;
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
    });
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});