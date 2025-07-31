<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class BusinessUserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BusinessUserCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/business-user');
        CRUD::setEntityNameStrings('user', 'users');
        
        // Set custom page titles
        $this->crud->setHeading('Business Users', 'list');
        $this->crud->setSubheading('Manage user accounts and wallet balances', 'list');
        $this->crud->setHeading('User Details', 'show');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::orderBy('id', 'desc');

        CRUD::column([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::column([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::column([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        CRUD::column([
            'name' => 'wallet_amount',
            'label' => 'Wallet Balance',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
            'priority' => 2,
        ]);

        CRUD::column([
            'name' => 'created_at',
            'label' => 'Registered',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s'
        ]);

        // Add quick stats columns
        CRUD::column([
            'name' => 'orders_count',
            'label' => 'Orders',
            'type' => 'custom_html',
            'value' => function($entry) {
                $count = $entry->orders()->count();
                return '<span class="badge badge-info">' . $count . '</span>';
            },
            'priority' => 3,
        ]);

        CRUD::column([
            'name' => 'transactions_count',
            'label' => 'Transactions',
            'type' => 'custom_html',
            'value' => function($entry) {
                $count = $entry->transactions()->where('status', 'active')->count();
                return '<span class="badge badge-success">' . $count . '</span>';
            },
            'priority' => 4,
        ]);
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        // Basic Information
        CRUD::column([
            'name' => 'id',
            'label' => 'User ID',
            'type' => 'number',
        ]);

        CRUD::column([
            'name' => 'name',
            'label' => 'Full Name',
            'type' => 'text',
        ]);

        CRUD::column([
            'name' => 'email',
            'label' => 'Email Address',
            'type' => 'email',
        ]);

        CRUD::column([
            'name' => 'wallet_amount',
            'label' => 'Current Wallet Balance',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
        ]);

        CRUD::column([
            'name' => 'created_at',
            'label' => 'Registration Date',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s'
        ]);

        CRUD::column([
            'name' => 'updated_at',
            'label' => 'Last Updated',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s'
        ]);

        // Financial Summary
        CRUD::column([
            'name' => 'financial_summary',
            'label' => 'Financial Summary',
            'type' => 'custom_html',
            'value' => function($entry) {
                $activeTransactions = $entry->transactions()->where('status', 'active')->count();
                $totalCredits = $entry->transactions()->where('type', 'credit')->where('status', 'active')->sum('amount');
                $totalDebits = $entry->transactions()->where('type', 'debit')->where('status', 'active')->sum('amount');
                
                return sprintf(
                    '<div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">💰 Financial Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Active Transactions:</strong> %d</p>
                                    <p><strong>Total Credits:</strong> <span class="text-success">$%s</span></p>
                                    <p><strong>Total Debits:</strong> <span class="text-danger">$%s</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Current Balance:</strong> <span class="text-primary">$%s</span></p>
                                    <p><strong>Net Activity:</strong> <span class="%s">$%s</span></p>
                                </div>
                            </div>
                            <a href="%s" class="btn btn-sm btn-outline-primary">
                                <i class="la la-list"></i> View User Transactions
                            </a>
                        </div>
                    </div>',
                    $activeTransactions,
                    number_format($totalCredits, 2),
                    number_format($totalDebits, 2),
                    number_format($entry->wallet_amount, 2),
                    ($totalCredits - $totalDebits) >= 0 ? 'text-success' : 'text-danger',
                    number_format($totalCredits - $totalDebits, 2),
                    backpack_url('transaction') . '?user_id=' . $entry->id
                );
            },
        ]);

        // Order Summary
        CRUD::column([
            'name' => 'order_summary',
            'label' => 'Order Activity',
            'type' => 'custom_html',
            'value' => function($entry) {
                $totalOrders = $entry->orders()->count();
                $completedOrders = $entry->orders()->where('status', 'completed')->count();
                $pendingOrders = $entry->orders()->where('status', 'pending_payment')->count();
                $cancelledOrders = $entry->orders()->where('status', 'cancelled')->count();
                $totalSpent = $entry->orders()->where('status', 'completed')->sum('amount');
                
                return sprintf(
                    '<div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">🛒 Order History</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Total Orders:</strong> %d</p>
                                    <p><strong>Completed:</strong> <span class="text-success">%d</span></p>
                                    <p><strong>Pending:</strong> <span class="text-warning">%d</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Cancelled:</strong> <span class="text-danger">%d</span></p>
                                    <p><strong>Total Spent:</strong> <span class="text-info">$%s</span></p>
                                </div>
                            </div>
                            <a href="%s" class="btn btn-sm btn-outline-primary">
                                <i class="la la-shopping-cart"></i> View User Orders
                            </a>
                        </div>
                    </div>',
                    $totalOrders,
                    $completedOrders,
                    $pendingOrders,
                    $cancelledOrders,
                    number_format($totalSpent, 2),
                    backpack_url('order') . '?user_id=' . $entry->id
                );
            },
        ]);

        // Account Status
        CRUD::column([
            'name' => 'account_status',
            'label' => 'Account Status',
            'type' => 'custom_html',
            'value' => function($entry) {
                $isActive = !$entry->deleted_at;
                $hasRecentActivity = $entry->orders()->where('created_at', '>', now()->subDays(30))->exists() ||
                                   $entry->transactions()->where('created_at', '>', now()->subDays(30))->exists();
                
                $statusBadge = $isActive ? 
                    '<span class="badge badge-success">Active</span>' : 
                    '<span class="badge badge-danger">Inactive</span>';
                
                $activityBadge = $hasRecentActivity ? 
                    '<span class="badge badge-info">Recent Activity</span>' : 
                    '<span class="badge badge-secondary">No Recent Activity</span>';
                
                return sprintf(
                    '<div>
                        <p><strong>Status:</strong> %s</p>
                        <p><strong>Activity:</strong> %s</p>
                        <p><strong>Last Login:</strong> %s</p>
                    </div>',
                    $statusBadge,
                    $activityBadge,
                    $entry->updated_at ? $entry->updated_at->diffForHumans() : 'Never'
                );
            },
        ]);
    }
}