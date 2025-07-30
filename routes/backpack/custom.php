<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('order', 'OrderCrudController');
    Route::post('order/search-users', 'OrderCrudController@searchUsers');
    Route::crud('transaction', 'TransactionCrudController');
    Route::post('transaction/check-balance', 'TransactionCrudController@checkBalance');
    
    // Pending Approvals routes
    Route::get('pending-approvals', 'PendingApprovalsController@index')->name('admin.pending-approvals.index');
    Route::get('pending-approvals/data', 'PendingApprovalsController@getPendingData')->name('admin.pending-approvals.data');
    Route::post('pending-approvals/approve-withdrawal/{order}', 'PendingApprovalsController@approveWithdrawal')->name('admin.pending-approvals.approve-withdrawal');
    Route::post('pending-approvals/deny-withdrawal/{order}', 'PendingApprovalsController@denyWithdrawal')->name('admin.pending-approvals.deny-withdrawal');
    Route::post('pending-approvals/bulk-approve-withdrawals', 'PendingApprovalsController@bulkApproveWithdrawals')->name('admin.pending-approvals.bulk-approve-withdrawals');
    Route::post('pending-approvals/bulk-deny-withdrawals', 'PendingApprovalsController@bulkDenyWithdrawals')->name('admin.pending-approvals.bulk-deny-withdrawals');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
