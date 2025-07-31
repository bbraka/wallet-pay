<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Requests\Admin\OrderRequest;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use App\Services\Merchant\OrdersService;
use App\Services\OrderService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Winex01\BackpackFilter\Http\Controllers\Operations\ExportOperation;
use Winex01\BackpackFilter\Http\Controllers\Operations\FilterOperation;
use Prologue\Alerts\Facades\Alert;

/**
 * Class OrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class OrderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use FilterOperation;
    use ExportOperation;

    protected OrdersService $ordersService;
    protected OrderService $orderService;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Order::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/order');
        CRUD::setEntityNameStrings('order', 'orders');
        
        $this->ordersService = app(OrdersService::class);
        $this->orderService = app(OrderService::class);
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

        // Columns
        CRUD::column([
            'name' => 'id',
            'label' => 'Order ID',
            'type' => 'number',
        ]);

        CRUD::column([
            'name' => 'created_at',
            'label' => 'Date',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s'
        ]);

        CRUD::column([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'enum',
        ]);

        CRUD::column([
            'name' => 'order_type',
            'label' => 'Type',
            'type' => 'enum',
        ]);

        CRUD::column([
            'name' => 'provider',
            'label' => 'Provider',
            'type' => 'model_function',
            'function_name' => 'getProviderName',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('topUpProvider', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                });
            }
        ]);

        CRUD::column([
            'name' => 'user_info',
            'label' => 'User',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->user) return '-';
                return sprintf(
                    '<a href="%s">%s<br><small>%s</small></a>',
                    backpack_url('user/'.$entry->user->id.'/show'),
                    e($entry->user->name),
                    e($entry->user->email)
                );
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%')
                      ->orWhere('email', 'like', '%'.$searchTerm.'%');
                });
            }
        ]);

        CRUD::column([
            'name' => 'receiver_info',
            'label' => 'Receiver',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->receiver) return '-';
                return sprintf(
                    '<a href="%s">%s<br><small>%s</small></a>',
                    backpack_url('user/'.$entry->receiver->id.'/show'),
                    e($entry->receiver->name),
                    e($entry->receiver->email)
                );
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('receiver', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%')
                      ->orWhere('email', 'like', '%'.$searchTerm.'%');
                });
            }
        ]);

        CRUD::column([
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
        ]);

        // Apply filter queries
        $this->filterQueries();
    }

    /**
     * Setup filter operation using winex01/backpack-filter
     */
    protected function setupFilterOperation()
    {
        // Status filter
        CRUD::field([
            'name' => 'status',
            'type' => 'select_from_array',
            'label' => 'Status',
            'options' => array_combine(
                array_column(OrderStatus::cases(), 'value'),
                array_map(fn($case) => $case->label(), OrderStatus::cases())
            ),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Order type filter
        CRUD::field([
            'name' => 'order_type',
            'type' => 'select_from_array',
            'label' => 'Type',
            'options' => array_combine(
                array_column(OrderType::cases(), 'value'),
                array_map(fn($case) => $case->label(), OrderType::cases())
            ),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Provider filter
        CRUD::field([
            'name' => 'top_up_provider_id',
            'label' => 'Provider',
            'type' => 'select_from_array',
            'options' => [null => 'All Providers'] + TopUpProvider::pluck('name', 'id')->toArray(),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Date range filter
        CRUD::field([
            'name' => 'date_range',
            'type' => 'date_range',
            'label' => 'Date Range',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // User filter
        CRUD::field([
            'name' => 'user_id',
            'label' => 'User',
            'type' => 'select_from_array',
            'options' => [null => 'All Users'] + User::pluck('email', 'id')->toArray(),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Receiver filter
        CRUD::field([
            'name' => 'receiver_user_id',
            'label' => 'Receiver',
            'type' => 'select_from_array',
            'options' => [null => 'All Receivers'] + User::pluck('email', 'id')->toArray(),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
    }

    /**
     * Apply filter queries
     */
    protected function filterQueries()
    {
        $request = request();

        // Status filter
        if ($request->has('status') && $request->get('status') !== null) {
            CRUD::addClause('where', 'status', $request->get('status'));
        }

        // Order type filter
        if ($request->has('order_type') && $request->get('order_type') !== null) {
            CRUD::addClause('where', 'order_type', $request->get('order_type'));
        }

        // Provider filter
        if ($request->has('provider') && $request->get('provider') !== null) {
            CRUD::addClause('where', 'top_up_provider_id', $request->get('provider'));
        }

        // Date range filter
        if ($request->has('date_range') && $request->get('date_range') !== null) {
            $dates = explode(' - ', $request->get('date_range'));
            if (count($dates) == 2) {
                CRUD::addClause('where', 'created_at', '>=', $dates[0]);
                CRUD::addClause('where', 'created_at', '<=', $dates[1] . ' 23:59:59');
            }
        }

        // User filter
        if ($request->has('user_id') && $request->get('user_id') !== null) {
            CRUD::addClause('where', 'user_id', $request->get('user_id'));
        }

        // Receiver filter
        if ($request->has('receiver_user_id') && $request->get('receiver_user_id') !== null) {
            CRUD::addClause('where', 'receiver_user_id', $request->get('receiver_user_id'));
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(OrderRequest::class);
        CRUD::setCreateContentClass('col-md-8');
        
        // Only allow creating top-up orders in admin
        CRUD::field([
            'name' => 'order_type',
            'type' => 'hidden',
            'value' => OrderType::ADMIN_TOP_UP->value,
        ]);

        CRUD::field([
            'name' => 'user_id',
            'label' => 'User',
            'type' => 'select_from_array',
            'options' => User::pluck('email', 'id')->toArray(),
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        CRUD::field([
            'name' => 'top_up_provider_id',
            'label' => 'Top-up Provider',
            'type' => 'select_from_array',
            'options' => TopUpProvider::where('is_active', true)->pluck('name', 'id')->toArray(),
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        CRUD::field([
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'prefix' => '$',
            'attributes' => [
                'step' => '0.01',
                'min' => '0.01',
                'max' => Order::MAX_TOP_UP_AMOUNT,
            ],
            'wrapper' => ['class' => 'form-group col-md-6'],
            'hint' => 'Maximum amount: $' . number_format(Order::MAX_TOP_UP_AMOUNT, 2),
        ]);


        CRUD::field([
            'name' => 'description',
            'label' => 'Description (Optional)',
            'type' => 'textarea',
            'wrapper' => ['class' => 'form-group col-md-12'],
            'hint' => 'Leave empty for auto-generated description',
            'default' => 'Admin wallet top-up',
        ]);

        CRUD::field([
            'name' => 'provider_reference',
            'label' => 'Provider Reference',
            'type' => 'text',
            'wrapper' => ['class' => 'form-group col-md-12'],
            'hint' => 'Optional reference number from the provider',
        ]);

        // Add custom script for client-side validation
        CRUD::field([
            'name' => 'separator',
            'type' => 'custom_html',
            'value' => '<script>
                $(document).ready(function() {
                    // Client-side validation for amount
                    $("#amount").on("input", function() {
                        var value = parseFloat($(this).val());
                        var max = ' . Order::MAX_TOP_UP_AMOUNT . ';
                        if (value > max) {
                            $(this).val(max);
                            new Noty({
                                type: "warning",
                                text: "Amount cannot exceed $" + max.toLocaleString()
                            }).show();
                        }
                    });
                });
            </script>'
        ]);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
        
        // Disable editing certain fields
        CRUD::field('user_id')->attributes(['disabled' => 'disabled']);
        
        // Only allow updating pending orders
        $entry = CRUD::getCurrentEntry();
        if ($entry && $entry->status !== OrderStatus::PENDING_PAYMENT) {
            CRUD::denyAccess('update');
            abort(403, 'Only pending orders can be updated.');
        }
    }

    /**
     * Store a newly created resource in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        $this->crud->setRequest($this->crud->validateRequest());
        $request = $this->crud->getRequest();
        
        // Use the service to create admin top-up
        $adminUser = backpack_user();
        $targetUser = User::findOrFail($request->input('user_id'));
        
        $data = $request->only(['amount', 'description', 'top_up_provider_id', 'provider_reference']);
        
        // Auto-generate title for admin top-ups
        $data['title'] = 'Admin Top-up - ' . $targetUser->email . ' - $' . number_format($data['amount'], 2);
        
        try {
            $order = $this->ordersService->createAdminTopUp($targetUser, $data, $adminUser);
            
            Alert::success('Order created successfully.')->flash();
            
            return $this->crud->performSaveAction($order->id);
        } catch (\Exception $e) {
            Alert::error($e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Update the specified resource in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $this->crud->setRequest($this->crud->validateRequest());
        $request = $this->crud->getRequest();
        $entry = $this->crud->getCurrentEntry();
        
        $data = $request->only(['title', 'amount', 'description', 'provider_reference']);
        
        try {
            $order = $this->ordersService->updateOrder($entry, $data);
            
            Alert::success('Order updated successfully.')->flash();
            
            return $this->crud->performSaveAction($order->id);
        } catch (\Exception $e) {
            Alert::error($e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Search users for select2 ajax
     */
    public function searchUsers()
    {
        $search = request()->get('q');
        
        return User::where(function($query) use ($search) {
                $query->where('email', 'like', '%'.$search.'%')
                      ->orWhere('name', 'like', '%'.$search.'%');
            })
            ->limit(20)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'text' => $user->email . ' (' . $user->name . ')'
                ];
            });
    }

    /**
     * Create admin withdrawal for a user
     */
    public function createWithdrawal(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01|max:' . Order::MAX_TRANSFER_AMOUNT,
            'description' => 'nullable|string|max:500'
        ]);

        try {
            $user = User::findOrFail($request->input('user_id'));
            
            $order = $this->orderService->processWithdrawalRequest(
                $user,
                $request->input('amount'),
                $request->input('description', 'Admin withdrawal'),
                OrderType::ADMIN_WITHDRAWAL
            );

            Alert::success("Admin withdrawal created successfully. Order ID: #{$order->id}")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            Alert::error("Failed to create withdrawal: {$e->getMessage()}")->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Approve a withdrawal order
     */
    public function approveWithdrawal(Order $order)
    {
        try {
            if (!$order->order_type->isWithdrawal()) {
                Alert::error('Order is not a withdrawal order.')->flash();
                return redirect()->back();
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                Alert::error('Order is not pending approval.')->flash();
                return redirect()->back();
            }

            $this->orderService->approveWithdrawal($order);
            
            Alert::success("Withdrawal #{$order->id} approved successfully.")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            Alert::error("Failed to approve withdrawal: {$e->getMessage()}")->flash();
            return redirect()->back();
        }
    }

    /**
     * Deny a withdrawal order
     */
    public function denyWithdrawal(Request $request, Order $order)
    {
        $request->validate([
            'denial_reason' => 'nullable|string|max:500'
        ]);

        try {
            if (!$order->order_type->isWithdrawal()) {
                Alert::error('Order is not a withdrawal order.')->flash();
                return redirect()->back();
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                Alert::error('Order is not pending approval.')->flash();
                return redirect()->back();
            }

            $this->orderService->denyWithdrawal($order, $request->input('denial_reason'));
            
            Alert::success("Withdrawal #{$order->id} denied successfully.")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            Alert::error("Failed to deny withdrawal: {$e->getMessage()}")->flash();
            return redirect()->back();
        }
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::column([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::column([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        CRUD::column([
            'name' => 'user_info',
            'label' => 'User',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->user) return '-';
                return sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    e($entry->user->name),
                    e($entry->user->email)
                );
            },
        ]);

        CRUD::column([
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
        ]);

        CRUD::column([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => array_combine(
                array_column(OrderStatus::cases(), 'value'),
                array_map(fn($case) => $case->label(), OrderStatus::cases())
            ),
        ]);

        CRUD::column([
            'name' => 'order_type',
            'label' => 'Type',
            'type' => 'select_from_array',
            'options' => array_combine(
                array_column(OrderType::cases(), 'value'),
                array_map(fn($case) => $case->label(), OrderType::cases())
            ),
        ]);

        CRUD::column([
            'name' => 'provider_info',
            'label' => 'Top-up Provider',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->topUpProvider) return '-';
                return sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    e($entry->topUpProvider->name),
                    e($entry->topUpProvider->code)
                );
            },
        ]);

        CRUD::column([
            'name' => 'provider_reference',
            'label' => 'Provider Reference',
            'type' => 'text',
        ]);

        CRUD::column([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
        ]);

        CRUD::column([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        CRUD::column([
            'name' => 'updated_at',
            'label' => 'Updated At',
            'type' => 'datetime',
        ]);
    }
}