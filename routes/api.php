<?php

use App\Http\Controllers\SchemaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// OpenAPI Schema endpoint
Route::get('/schema', [SchemaController::class, 'index'])->name('api.schema');