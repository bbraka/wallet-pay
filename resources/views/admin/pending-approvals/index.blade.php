@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
        trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'admin'),
        'Pending Approvals' => false,
    ];
    $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
    <section class="container-flui<!-- Bulk Reject Payments Modal -->
<div class="modal fade" id="bulkRejectPaymentsModal" tabindex="-1" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">>
        <h2>
            <span class="text-capitalize">Pending Approvals</span>
            <small>Manage pending withdrawals and payments</small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-bs-dismiss="alert" aria-hidden="true">&times;</button>
                <h5><i class="icon fas fa-check"></i> Success!</h5>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-bs-dismiss="alert" aria-hidden="true">&times;</button>
                <h5><i class="icon fas fa-ban"></i> Error!</h5>
                {{ session('error') }}
            </div>
        @endif

        <!-- Pending Withdrawals -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-money-bill-wave"></i> 
                    Pending Withdrawals ({{ $pendingWithdrawals->total() }})
                </h3>
                <div class="card-tools">
                    @if($pendingWithdrawals->count() > 0)
                        <button type="button" class="btn btn-success btn-sm" id="bulk-approve-btn">
                            <i class="fa fa-check"></i> Bulk Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulk-deny-btn">
                            <i class="fa fa-times"></i> Bulk Deny
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($pendingWithdrawals->count() > 0)
                    <form id="bulk-action-form" method="POST">
                        @csrf
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="select-all-withdrawals">
                                    </th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Created At</th>
                                    <th>Description</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingWithdrawals as $withdrawal)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="order_ids[]" value="{{ $withdrawal->id }}" class="withdrawal-checkbox">
                                        </td>
                                        <td>
                                            <strong>#{{ $withdrawal->id }}</strong>
                                        </td>
                                        <td>
                                            {{ $withdrawal->user->name }}<br>
                                            <small class="text-muted">{{ $withdrawal->user->email }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning">${{ number_format($withdrawal->amount, 2) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $withdrawal->order_type === \App\Enums\OrderType::USER_WITHDRAWAL ? 'badge-info' : 'badge-secondary' }}">
                                                {{ $withdrawal->order_type->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $withdrawal->created_at->format('M d, Y H:i') }}
                                        </td>
                                        <td>
                                            {{ Str::limit($withdrawal->description ?? 'No description', 50) }}
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-sm" onclick="submitApprovalForm({{ $withdrawal->id }})">
                                                    <i class="fa fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="showDenyModal({{ $withdrawal->id }})">
                                                    <i class="fa fa-times"></i> Deny
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </form>
                    
                    <!-- Individual approval forms (hidden) -->
                    @foreach($pendingWithdrawals as $withdrawal)
                        <form id="approve-form-{{ $withdrawal->id }}" method="POST" action="{{ route('admin.pending-approvals.approve-withdrawal', $withdrawal->id) }}" style="display: none;">
                            @csrf
                        </form>
                    @endforeach
                    
                    <div class="card-footer">
                        {{ $pendingWithdrawals->links() }}
                    </div>
                @else
                    <div class="card-body text-center">
                        <p class="text-muted">No pending withdrawals found.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Pending Payments -->
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-credit-card"></i> 
                    Pending Payments ({{ $pendingPayments->total() }})
                </h3>
                <div class="card-tools">
                    @if($pendingPayments->count() > 0)
                        <button type="button" class="btn btn-success btn-sm" id="bulk-approve-payments-btn">
                            <i class="fa fa-check"></i> Bulk Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulk-reject-payments-btn">
                            <i class="fa fa-times"></i> Bulk Reject
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($pendingPayments->count() > 0)
                    <form id="bulk-payment-action-form" method="POST">
                        @csrf
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="select-all-payments">
                                    </th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Created At</th>
                                    <th>Details</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingPayments as $payment)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="order_ids[]" value="{{ $payment->id }}" class="payment-checkbox">
                                        </td>
                                        <td>
                                            <strong>#{{ $payment->id }}</strong>
                                        </td>
                                        <td>
                                            {{ $payment->user->name }}<br>
                                            <small class="text-muted">{{ $payment->user->email }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">${{ number_format($payment->amount, 2) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $payment->order_type->label() }}</span>
                                        </td>
                                        <td>
                                            {{ $payment->created_at->format('M d, Y H:i') }}
                                        </td>
                                        <td>
                                            @if($payment->receiver)
                                                <strong>To:</strong> {{ $payment->receiver->name }} ({{ $payment->receiver->email }})
                                            @elseif($payment->topUpProvider)
                                                <strong>Provider:</strong> {{ $payment->topUpProvider->name }}
                                            @endif
                                            @if($payment->provider_reference)
                                                <br><strong>Ref:</strong> {{ $payment->provider_reference }}
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-sm" onclick="submitPaymentApprovalForm({{ $payment->id }})">
                                                    <i class="fa fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="showRejectPaymentModal({{ $payment->id }})">
                                                    <i class="fa fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </form>
                    
                    <!-- Individual payment approval forms (hidden) -->
                    @foreach($pendingPayments as $payment)
                        <form id="approve-payment-form-{{ $payment->id }}" method="POST" action="{{ route('admin.pending-approvals.approve-payment', $payment->id) }}" style="display: none;">
                            @csrf
                        </form>
                    @endforeach
                    
                    <div class="card-footer">
                        {{ $pendingPayments->links() }}
                    </div>
                @else
                    <div class="card-body text-center">
                        <p class="text-muted">No pending payments found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Deny Modal -->
<div class="modal fade" id="denyModal" tabindex="-1" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="deny-form" method="POST">
                @csrf
                <div class="modal-header">
                    <h4 class="modal-title">Deny Withdrawal</h4>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="denial_reason">Reason for denial (optional):</label>
                        <textarea name="denial_reason" id="denial_reason" class="form-control" rows="3" placeholder="Enter reason for denial..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deny Withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Deny Modal -->
<div class="modal fade" id="bulkDenyModal" tabindex="-1" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="bulk-deny-form" method="POST" action="{{ route('admin.pending-approvals.bulk-deny-withdrawals') }}">
                @csrf
                <div class="modal-header">
                    <h4 class="modal-title">Bulk Deny Withdrawals</h4>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to deny <span id="selected-count">0</span> selected withdrawal(s)?</p>
                    <div class="form-group">
                        <label for="bulk_denial_reason">Reason for denial (optional):</label>
                        <textarea name="bulk_denial_reason" id="bulk_denial_reason" class="form-control" rows="3" placeholder="Enter reason for denial..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deny Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Payment Modal -->
<div class="modal fade" id="rejectPaymentModal" tabindex="-1" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="reject-payment-form" method="POST">
                @csrf
                <div class="modal-header">
                    <h4 class="modal-title">Reject Payment</h4>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Reason for rejection (optional):</label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reject Payments Modal -->
<div class="modal fade" id="bulkRejectPaymentsModal" tabindex="-1" role="dialog" data-backdrop="true" data-keyboard="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="bulk-reject-payments-form" method="POST" action="{{ route('admin.pending-approvals.bulk-reject-payments') }}">
                @csrf
                <div class="modal-header">
                    <h4 class="modal-title">Bulk Reject Payments</h4>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject <span id="selected-payments-count">0</span> selected payment(s)?</p>
                    <div class="form-group">
                        <label for="bulk_rejection_reason">Reason for rejection (optional):</label>
                        <textarea name="bulk_rejection_reason" id="bulk_rejection_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('after_scripts')
<script>
$(document).ready(function() {
    // Select all withdrawals checkbox
    $('#select-all-withdrawals').change(function() {
        $('.withdrawal-checkbox').prop('checked', this.checked);
        updateBulkActionButtons();
    });

    // Individual withdrawal checkbox
    $('.withdrawal-checkbox').change(function() {
        updateBulkActionButtons();
    });

    // Select all payments checkbox
    $('#select-all-payments').change(function() {
        $('.payment-checkbox').prop('checked', this.checked);
        updateBulkPaymentActionButtons();
    });

    // Individual payment checkbox
    $('.payment-checkbox').change(function() {
        updateBulkPaymentActionButtons();
    });

    // Update bulk action button states for withdrawals
    function updateBulkActionButtons() {
        const selectedCount = $('.withdrawal-checkbox:checked').length;
        $('#bulk-approve-btn, #bulk-deny-btn').prop('disabled', selectedCount === 0);
        $('#selected-count').text(selectedCount);
    }

    // Update bulk action button states for payments
    function updateBulkPaymentActionButtons() {
        const selectedCount = $('.payment-checkbox:checked').length;
        $('#bulk-approve-payments-btn, #bulk-reject-payments-btn').prop('disabled', selectedCount === 0);
        $('#selected-payments-count').text(selectedCount);
    }

    // Bulk approve withdrawals
    $('#bulk-approve-btn').click(function() {
        const selectedIds = $('.withdrawal-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            return;
        }

        if (confirm(`Are you sure you want to approve ${selectedIds.length} withdrawal(s)?`)) {
            const form = $('#bulk-action-form');
            form.attr('action', '{{ route("admin.pending-approvals.bulk-approve-withdrawals") }}');
            form.submit();
        }
    });

    // Bulk deny withdrawals
    $('#bulk-deny-btn').click(function() {
        const selectedIds = $('.withdrawal-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            return;
        }

        // Copy selected IDs to bulk deny form
        $('#bulk-deny-form').find('input[name="order_ids[]"]').remove();
        selectedIds.forEach(function(id) {
            $('#bulk-deny-form').append('<input type="hidden" name="order_ids[]" value="' + id + '">');
        });

        $('#bulkDenyModal').modal('show');
    });

    // Bulk approve payments
    $('#bulk-approve-payments-btn').click(function() {
        const selectedIds = $('.payment-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            return;
        }

        if (confirm(`Are you sure you want to approve ${selectedIds.length} payment(s)?`)) {
            const form = $('#bulk-payment-action-form');
            form.attr('action', '{{ route("admin.pending-approvals.bulk-approve-payments") }}');
            form.submit();
        }
    });

    // Bulk reject payments
    $('#bulk-reject-payments-btn').click(function() {
        const selectedIds = $('.payment-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            return;
        }

        // Copy selected IDs to bulk reject payments form
        $('#bulk-reject-payments-form').find('input[name="order_ids[]"]').remove();
        selectedIds.forEach(function(id) {
            $('#bulk-reject-payments-form').append('<input type="hidden" name="order_ids[]" value="' + id + '">');
        });

        $('#bulkRejectPaymentsModal').modal('show');
    });

    // Initialize button states
    updateBulkActionButtons();
    updateBulkPaymentActionButtons();
});

function submitApprovalForm(orderId) {
    if (confirm('Are you sure you want to approve this withdrawal?')) {
        document.getElementById('approve-form-' + orderId).submit();
    }
}

function submitPaymentApprovalForm(orderId) {
    if (confirm('Are you sure you want to approve this payment?')) {
        document.getElementById('approve-payment-form-' + orderId).submit();
    }
}

function showDenyModal(orderId) {
    const form = $('#deny-form');
    form.attr('action', '{{ url("admin/pending-approvals/deny-withdrawal") }}/' + orderId);
    $('#denyModal').modal('show');
}

function showRejectPaymentModal(orderId) {
    const form = $('#reject-payment-form');
    form.attr('action', '{{ url("admin/pending-approvals/reject-payment") }}/' + orderId);
    $('#rejectPaymentModal').modal('show');
}


    $(document).ready(function () {
        // Removed unnecessary modal backdrop manipulation
        // Bootstrap 5 handles modal z-index properly
    });
</script>
@endsection