<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/merchant');
});

// Merchant SPA routes - catch all routes under /merchant and serve the React app
Route::get('/merchant/{path?}', function () {
    return view('merchant');
})->where('path', '.*');
