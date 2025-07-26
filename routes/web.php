<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'User Wallet App - Scaffolding Complete']);
});
