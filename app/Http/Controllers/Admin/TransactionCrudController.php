<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\Admin\TransactionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\TransactionService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Winex01\BackpackFilter\Http\Controllers\Operations\ExportOperation;
use Winex01\BackpackFilter\Http\Controllers\Operations\FilterOperation;
use Prologue\Alerts\Facades\Alert;
use Illuminate\Support\Str;

/**
 * Class TransactionCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class TransactionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use FilterOperation;
    use ExportOperation;

    protected TransactionService $transactionService;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Transaction::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/transaction');
        CRUD::setEntityNameStrings('transaction', 'transactions');
        
        $this->transactionService = app(TransactionService::class);
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
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::column([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'enum',
        ]);

        CRUD::column([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'enum',
        ]);

        CRUD::column([
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'prefix' => '$',
            'decimals' => 2,
        ]);

        CRUD::column([
            'name' => 'description_excerpt',
            'label' => 'Description',
            'type' => 'text',
            'value' => function($entry) {
                return \Str::limit($entry->description, 50);
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('description', 'like', '%'.$searchTerm.'%');
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
            'name' => 'creator_info',
            'label' => 'Created By',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->creator) return 'System';
                return sprintf(
                    '<a href="%s">%s</a>',
                    backpack_url('user/'.$entry->creator->id.'/show'),
                    e($entry->creator->email)
                );
            },
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('creator', function ($q) use ($searchTerm) {
                    $q->where('email', 'like', '%'.$searchTerm.'%');
                });
            }
        ]);

        CRUD::column([
            'name' => 'order_link',
            'label' => 'Order',
            'type' => 'custom_html',
            'value' => function($entry) {
                if (!$entry->order_id) return '-';
                return sprintf(
                    '<a href="%s">#%d</a>',
                    backpack_url('order/'.$entry->order_id.'/show'),
                    $entry->order_id
                );
            },
        ]);

        CRUD::column([
            'name' => 'created_at',
            'label' => 'Date',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s'
        ]);

        // Apply filter queries
        $this->filterQueries();

        // Custom buttons
        $this->setupButtons();
    }

    protected function setupButtons()
    {
        // Remove delete button for system transactions
        CRUD::addButtonFromModelFunction('line', 'delete', 'getDeleteButton', 'end');
    }

    /**
     * Setup filter operation using winex01/backpack-filter
     */
    protected function setupFilterOperation()
    {
        // Type filter
        CRUD::field([
            'name' => 'type',
            'type' => 'select_from_array',
            'label' => 'Type',
            'options' => array_combine(
                array_column(TransactionType::cases(), 'value'),
                array_map(fn($case) => $case->label(), TransactionType::cases())
            ),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Status filter
        CRUD::field([
            'name' => 'status',
            'type' => 'select_from_array',
            'label' => 'Status',
            'options' => array_combine(
                array_column(TransactionStatus::cases(), 'value'),
                array_map(fn($case) => $case->label(), TransactionStatus::cases())
            ),
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

        // Created by filter
        CRUD::field([
            'name' => 'created_by',
            'label' => 'Created By',
            'type' => 'select_from_array',
            'options' => [null => 'All Creators'] + User::pluck('email', 'id')->toArray(),
            'allows_null' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // Order ID filter
        CRUD::field([
            'name' => 'order_id',
            'type' => 'number',
            'label' => 'Order ID',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
    }

    /**
     * Apply filter queries
     */
    protected function filterQueries()
    {
        $request = request();

        // Type filter
        if ($request->has('type') && $request->get('type') !== null) {
            CRUD::addClause('where', 'type', $request->get('type'));
        }

        // Status filter
        if ($request->has('status') && $request->get('status') !== null) {
            CRUD::addClause('where', 'status', $request->get('status'));
        }

        // Date range filter
        if ($request->has('date_range') && $request->get('date_range') !== null) {
            $dates = explode(' - ', $request->get('date_range'));
            if (count($dates) == 2) {
                CRUD::addClause('where', 'created_at', '>=', $dates[0]);
                CRUD::addClause('where', 'created_at', '<=', $dates[1] . ' 23:59:59');
            }
        }

        // Created by filter
        if ($request->has('created_by') && $request->get('created_by') !== null) {
            CRUD::addClause('where', 'created_by', $request->get('created_by'));
        }

        // Order ID filter
        if ($request->has('order_id') && $request->get('order_id') !== null) {
            CRUD::addClause('where', 'order_id', $request->get('order_id'));
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
        CRUD::setValidation(TransactionRequest::class);
        CRUD::setCreateContentClass('col-md-8');

        CRUD::field([
            'name' => 'user_id',
            'label' => 'User',
            'type' => 'select_from_array',
            'options' => User::pluck('email', 'id')->toArray(),
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        CRUD::field([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'select_from_array',
            'options' => array_combine(
                array_column(TransactionType::cases(), 'value'),
                array_map(fn($case) => $case->label(), TransactionType::cases())
            ),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::field([
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'prefix' => '$',
            'attributes' => [
                'step' => '0.01',
                'min' => '0.01',
            ],
            'wrapper' => ['class' => 'form-group col-md-6'],
            'hint' => 'Enter positive amount. It will be automatically adjusted based on type.',
        ]);

        CRUD::field([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
            'wrapper' => ['class' => 'form-group col-md-12'],
        ]);

        // Hidden field for created_by
        CRUD::field([
            'name' => 'created_by',
            'type' => 'hidden',
            'value' => backpack_user()->id,
        ]);

        // Add custom script for client-side validation and amount handling
        CRUD::field([
            'name' => 'separator',
            'type' => 'custom_html',
            'value' => '<script>
                $(document).ready(function() {
                    var $userSelect = $("[name=user_id]");
                    var $typeSelect = $("[name=type]");
                    var $amountInput = $("[name=amount]");
                    
                    // Force positive input
                    $amountInput.on("input", function() {
                        var value = parseFloat($(this).val());
                        if (value < 0) {
                            $(this).val(Math.abs(value));
                        }
                    });
                    
                    // Check balance for debit transactions
                    function checkBalance() {
                        var userId = $userSelect.val();
                        var type = $typeSelect.val();
                        var amount = parseFloat($amountInput.val());
                        
                        if (userId && type === "debit" && amount > 0) {
                            $.ajax({
                                url: "' . backpack_url('transaction/check-balance') . '",
                                method: "POST",
                                data: {
                                    user_id: userId,
                                    amount: amount,
                                    _token: "' . csrf_token() . '"
                                },
                                success: function(response) {
                                    if (!response.sufficient) {
                                        new Noty({
                                            type: "error",
                                            text: "Insufficient balance. User has $" + response.balance.toFixed(2)
                                        }).show();
                                        $amountInput.val(response.balance);
                                    }
                                }
                            });
                        }
                    }
                    
                    $typeSelect.on("change", checkBalance);
                    $amountInput.on("blur", checkBalance);
                    $userSelect.on("change", checkBalance);
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
        CRUD::field('type')->attributes(['disabled' => 'disabled']);
        
        // Check if transaction can be edited
        $entry = CRUD::getCurrentEntry();
        if ($entry && !$entry->created_by) {
            CRUD::denyAccess('update');
            abort(403, 'System-generated transactions cannot be modified.');
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
        
        $adminUser = backpack_user();
        
        $data = $request->only(['user_id', 'type', 'amount', 'description']);
        
        try {
            $transaction = $this->transactionService->createManualTransaction($data, $adminUser);
            
            Alert::success('Transaction created successfully.')->flash();
            
            return $this->crud->performSaveAction($transaction->id);
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
        
        $data = $request->only(['amount', 'description']);
        
        try {
            $transaction = $this->transactionService->updateTransaction($entry, $data);
            
            Alert::success('Transaction updated successfully.')->flash();
            
            return $this->crud->performSaveAction($transaction->id);
        } catch (\Exception $e) {
            Alert::error($e->getMessage())->flash();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        
        $entry = $this->crud->getEntry($id);
        
        if (!$this->transactionService->canDeleteTransaction($entry)) {
            Alert::error('Only manual transactions can be deleted.')->flash();
            return redirect()->back();
        }
        
        return $this->crud->delete($id);
    }

    /**
     * Check user balance for AJAX requests
     */
    public function checkBalance()
    {
        $userId = request()->input('user_id');
        $amount = request()->input('amount');
        
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['sufficient' => false, 'balance' => 0]);
        }
        
        return response()->json([
            'sufficient' => $user->wallet_amount >= $amount,
            'balance' => $user->wallet_amount
        ]);
    }
}