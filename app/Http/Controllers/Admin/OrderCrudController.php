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

        // Filters - Using open-source alternatives instead of PRO filters
        $this->setupOpenSourceFilters();
    }

    protected function setupOpenSourceFilters()
    {
        // Instead of PRO filters, we'll handle filtering via URL parameters
        $request = request();
        
        // Status filter via URL parameter
        if ($request->has('status') && $request->get('status') !== '') {
            CRUD::addClause('where', 'status', $request->get('status'));
        }
        
        // Order type filter via URL parameter
        if ($request->has('order_type') && $request->get('order_type') !== '') {
            CRUD::addClause('where', 'order_type', $request->get('order_type'));
        }
        
        // Provider filter via URL parameter
        if ($request->has('provider') && $request->get('provider') !== '') {
            CRUD::addClause('where', 'top_up_provider_id', $request->get('provider'));
        }
        
        // Date range filter via URL parameters
        if ($request->has('from') && $request->get('from') !== '') {
            CRUD::addClause('where', 'created_at', '>=', $request->get('from'));
        }
        
        if ($request->has('to') && $request->get('to') !== '') {
            CRUD::addClause('where', 'created_at', '<=', $request->get('to') . ' 23:59:59');
        }
        
        // User filter via URL parameter
        if ($request->has('user_id') && $request->get('user_id') !== '') {
            CRUD::addClause('where', 'user_id', $request->get('user_id'));
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
            'type' => 'select2',
            'entity' => 'user',
            'model' => User::class,
            'attribute' => 'email',
            'options' => (function ($query) {
                return $query->orderBy('email', 'ASC')->get();
            }),
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        CRUD::field([
            'name' => 'top_up_provider_id',
            'label' => 'Top-up Provider',
            'type' => 'select2',
            'entity' => 'topUpProvider',
            'model' => TopUpProvider::class,
            'attribute' => 'name',
            'options' => (function ($query) {
                return $query->where('is_active', true)->orderBy('name', 'ASC')->get();
            }),
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
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        CRUD::field([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
            'wrapper' => ['class' => 'form-group col-md-12'],
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
        
        $data = $request->only(['title', 'amount', 'description', 'top_up_provider_id', 'provider_reference']);
        
        try {
            $order = $this->ordersService->createAdminTopUp($targetUser, $data, $adminUser);
            
            \Alert::success('Order created successfully.')->flash();
            
            return $this->crud->performSaveAction($order->id);
        } catch (\Exception $e) {
            \Alert::error($e->getMessage())->flash();
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
            
            \Alert::success('Order updated successfully.')->flash();
            
            return $this->crud->performSaveAction($order->id);
        } catch (\Exception $e) {
            \Alert::error($e->getMessage())->flash();
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

            \Alert::success("Admin withdrawal created successfully. Order ID: #{$order->id}")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Alert::error("Failed to create withdrawal: {$e->getMessage()}")->flash();
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
                \Alert::error('Order is not a withdrawal order.')->flash();
                return redirect()->back();
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                \Alert::error('Order is not pending approval.')->flash();
                return redirect()->back();
            }

            $this->orderService->approveWithdrawal($order);
            
            \Alert::success("Withdrawal #{$order->id} approved successfully.")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Alert::error("Failed to approve withdrawal: {$e->getMessage()}")->flash();
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
                \Alert::error('Order is not a withdrawal order.')->flash();
                return redirect()->back();
            }

            if ($order->status !== OrderStatus::PENDING_APPROVAL) {
                \Alert::error('Order is not pending approval.')->flash();
                return redirect()->back();
            }

            $this->orderService->denyWithdrawal($order, $request->input('denial_reason'));
            
            \Alert::success("Withdrawal #{$order->id} denied successfully.")->flash();
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Alert::error("Failed to deny withdrawal: {$e->getMessage()}")->flash();
            return redirect()->back();
        }
    }
}