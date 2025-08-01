import React, { useState, useEffect, forwardRef, useImperativeHandle, ChangeEvent, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { OrdersApi, MerchantAuthenticationApi, Order, User } from '../../generated/src';
import { apiConfig } from '../../config/api';

interface WalletPageRef {
    refreshData: () => void;
}

interface Filters {
    date_from: string;
    date_to: string;
    status: string;
    min_amount: string;
    max_amount: string;
}

interface WalletPageProps {}

const WalletPage = forwardRef<WalletPageRef, WalletPageProps>((props, ref) => {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [currentUser, setCurrentUser] = useState<User | null>(user);
    const [orders, setOrders] = useState<Order[]>([]);
    const [pendingTransfers, setPendingTransfers] = useState<Order[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [pendingTransfersLoading, setPendingTransfersLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [filters, setFilters] = useState<Filters>({
        date_from: '',
        date_to: '',
        status: '',
        min_amount: '',
        max_amount: ''
    });

    useEffect(() => {
        loadUserData();
        loadOrders();
        loadPendingTransfers();
    }, []);

    // Expose refresh function to parent component
    useImperativeHandle(ref, () => ({
        refreshData: () => {
            loadUserData();
            loadOrders();
            loadPendingTransfers();
        }
    }));

    const loadUserData = async (): Promise<void> => {
        try {
            const authApi = new MerchantAuthenticationApi(apiConfig.getConfiguration());
            const response = await authApi.getMerchantUser();
            
            if (response.success) {
                setCurrentUser(response.user);
            }
        } catch (err) {
            console.error('Failed to load user data:', err);
        }
    };

    const loadOrders = async (filterParams: Partial<Filters> = {}): Promise<void> => {
        try {
            setLoading(true);
            setError('');
            
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.getMerchantOrders({
                ...filters,
                ...filterParams
            });
            
            setOrders(response.data || []);
        } catch (err: any) {
            setError(err.message || 'Failed to load orders');
        } finally {
            setLoading(false);
        }
    };

    const loadPendingTransfers = async (): Promise<void> => {
        try {
            setPendingTransfersLoading(true);
            
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.getMerchantPendingTransfers();
            
            setPendingTransfers(Array.isArray(response) ? response : []);
        } catch (err: any) {
            console.error('Failed to load pending transfers:', err);
            setPendingTransfers([]);
        } finally {
            setPendingTransfersLoading(false);
        }
    };

    const handleFilterChange = (field: keyof Filters, value: string): void => {
        const newFilters = { ...filters, [field]: value };
        setFilters(newFilters);
    };

    const handleFilterSubmit = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        loadOrders(filters);
    };

    const handleFilterReset = (): void => {
        const resetFilters: Filters = {
            date_from: '',
            date_to: '',
            status: '',
            min_amount: '',
            max_amount: ''
        };
        setFilters(resetFilters);
        loadOrders(resetFilters);
    };

    const formatAmount = (amount: number): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getStatusBadgeClass = (status: string): string => {
        switch (status) {
            case 'completed':
                return 'badge-success';
            case 'pending_payment':
                return 'badge-warning';
            case 'pending_approval':
                return 'badge-info';
            case 'cancelled':
                return 'badge-secondary';
            case 'refunded':
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    };

    const getOrderTypeDisplay = (orderType: string): string => {
        switch (orderType) {
            case 'internal_transfer':
                return 'Transfer';
            case 'user_top_up':
                return 'Top Up';
            case 'admin_top_up':
                return 'Admin Top Up';
            case 'user_withdrawal':
                return 'Withdrawal';
            case 'admin_withdrawal':
                return 'Admin Withdrawal';
            default:
                return orderType;
        }
    };

    const handleViewTransaction = (orderId: number): void => {
        navigate(`/transaction/${orderId}`);
    };

    const handleCancelOrder = async (order: Order): Promise<void> => {
        const confirmed = window.confirm(
            `Are you sure you want to cancel this ${getOrderTypeDisplay(order.orderType)}?\n\n` +
            `Order ID: #${order.id}\n` +
            `Amount: ${formatAmount(order.amount)}\n` +
            `Title: ${order.title}\n\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            await ordersApi.cancelMerchantOrder({
                order: order.id
            });
            
            // Show success message
            alert('Order has been successfully cancelled.');
            
            // Refresh the orders list
            loadOrders();
            
        } catch (err: any) {
            alert(`Failed to cancel order: ${err.message || 'Unknown error'}`);
        }
    };

    const handleConfirmTransfer = async (order: Order): Promise<void> => {
        const confirmed = window.confirm(
            `Are you sure you want to confirm this transfer?\n\n` +
            `From: ${order.user?.name || order.user?.email || 'Unknown'}\n` +
            `Amount: ${formatAmount(order.amount)}\n` +
            `Title: ${order.title}\n\n` +
            `This will add the funds to your wallet.`
        );

        if (!confirmed) return;

        try {
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            await ordersApi.confirmMerchantOrder({
                order: order.id
            });
            
            alert('Transfer has been confirmed successfully!');
            
            // Refresh data
            loadUserData();
            loadOrders();
            loadPendingTransfers();
            
        } catch (err: any) {
            alert(`Failed to confirm transfer: ${err.message || 'Unknown error'}`);
        }
    };

    const handleRejectTransfer = async (order: Order): Promise<void> => {
        const confirmed = window.confirm(
            `Are you sure you want to reject this transfer?\n\n` +
            `From: ${order.user?.name || order.user?.email || 'Unknown'}\n` +
            `Amount: ${formatAmount(order.amount)}\n` +
            `Title: ${order.title}\n\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            await ordersApi.rejectMerchantOrder({
                order: order.id
            });
            
            alert('Transfer has been rejected successfully.');
            
            // Refresh data
            loadPendingTransfers();
            
        } catch (err: any) {
            alert(`Failed to reject transfer: ${err.message || 'Unknown error'}`);
        }
    };

    if (loading && orders.length === 0) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12">
                        <div className="text-center">
                            <div className="spinner-border" role="status">
                                <span className="sr-only">Loading...</span>
                            </div>
                            <p className="mt-2">Loading wallet data...</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="container-fluid py-4">
            {/* Wallet Balance Card */}
            <div className="row mb-4">
                <div className="col-12 col-md-6 col-lg-4">
                    <div className="card bg-primary text-white">
                        <div className="card-body">
                            <div className="d-flex align-items-center">
                                <div className="flex-grow-1">
                                    <h5 className="card-title mb-0">Wallet Balance</h5>
                                    <h2 className="mb-0">{formatAmount(currentUser?.walletAmount || 0)}</h2>
                                </div>
                                <div className="ml-3">
                                    <i className="fas fa-wallet fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="row mb-4">
                <div className="col-12">
                    <div className="card">
                        <div className="card-body">
                            <h5 className="card-title">Filter Transactions</h5>
                            <form onSubmit={handleFilterSubmit}>
                                <div className="row">
                                    <div className="col-md-6 col-lg-3 mb-3">
                                        <label htmlFor="date_from" className="form-label">From Date</label>
                                        <input
                                            type="date"
                                            className="form-control"
                                            id="date_from"
                                            value={filters.date_from}
                                            onChange={(e: ChangeEvent<HTMLInputElement>) => handleFilterChange('date_from', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-6 col-lg-3 mb-3">
                                        <label htmlFor="date_to" className="form-label">To Date</label>
                                        <input
                                            type="date"
                                            className="form-control"
                                            id="date_to"
                                            value={filters.date_to}
                                            onChange={(e: ChangeEvent<HTMLInputElement>) => handleFilterChange('date_to', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-6 col-lg-2 mb-3">
                                        <label htmlFor="status" className="form-label">Status</label>
                                        <select
                                            className="form-control"
                                            id="status"
                                            value={filters.status}
                                            onChange={(e: ChangeEvent<HTMLSelectElement>) => handleFilterChange('status', e.target.value)}
                                        >
                                            <option value="">All Status</option>
                                            <option value="pending_payment">Pending Payment</option>
                                            <option value="pending_approval">Pending Approval</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                            <option value="refunded">Refunded</option>
                                        </select>
                                    </div>
                                    <div className="col-md-6 col-lg-2 mb-3">
                                        <label htmlFor="min_amount" className="form-label">Min Amount</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="form-control"
                                            id="min_amount"
                                            placeholder="0.00"
                                            value={filters.min_amount}
                                            onChange={(e: ChangeEvent<HTMLInputElement>) => handleFilterChange('min_amount', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-6 col-lg-2 mb-3">
                                        <label htmlFor="max_amount" className="form-label">Max Amount</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="form-control"
                                            id="max_amount"
                                            placeholder="0.00"
                                            value={filters.max_amount}
                                            onChange={(e: ChangeEvent<HTMLInputElement>) => handleFilterChange('max_amount', e.target.value)}
                                        />
                                    </div>
                                </div>
                                <div className="row">
                                    <div className="col-12">
                                        <button type="submit" className="btn btn-primary mr-2" disabled={loading}>
                                            {loading ? (
                                                <>
                                                    <span className="spinner-border spinner-border-sm mr-2" role="status"></span>
                                                    Filtering...
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fas fa-search mr-2"></i>
                                                    Apply Filters
                                                </>
                                            )}
                                        </button>
                                        <button 
                                            type="button" 
                                            className="btn btn-secondary" 
                                            onClick={handleFilterReset}
                                            disabled={loading}
                                        >
                                            <i className="fas fa-times mr-2"></i>
                                            Reset
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {/* Error Message */}
            {error && (
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="alert alert-danger" role="alert">
                            <i className="fas fa-exclamation-triangle mr-2"></i>
                            {error}
                        </div>
                    </div>
                </div>
            )}

            {/* Pending Transfers Section */}
            {!pendingTransfersLoading && pendingTransfers.length > 0 && (
                <div className="row mb-4">
                    <div className="col-12">
                        <div className="card border-warning">
                            <div className="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                <h5 className="mb-0">
                                    <i className="fas fa-clock mr-2"></i>
                                    Pending Transfers
                                </h5>
                                <span className="badge badge-dark">{pendingTransfers.length} pending</span>
                            </div>
                            <div className="card-body p-0">
                                <div className="table-responsive">
                                    <table className="table table-hover mb-0">
                                        <thead className="thead-light">
                                            <tr>
                                                <th>From</th>
                                                <th>Title</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {pendingTransfers.map((transfer) => (
                                                <tr key={transfer.id}>
                                                    <td>
                                                        <div>
                                                            <strong>{transfer.user?.name || 'Unknown'}</strong>
                                                        </div>
                                                        <small className="text-muted">
                                                            {transfer.user?.email || 'No email'}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div className="text-truncate" style={{ maxWidth: '200px' }}>
                                                            {transfer.title}
                                                        </div>
                                                        {transfer.description && (
                                                            <small className="text-muted d-block text-truncate" style={{ maxWidth: '200px' }}>
                                                                {transfer.description}
                                                            </small>
                                                        )}
                                                    </td>
                                                    <td className="font-weight-bold text-success">
                                                        {formatAmount(transfer.amount)}
                                                    </td>
                                                    <td>
                                                        <small>
                                                            {formatDate(transfer.createdAt)}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div className="btn-group btn-group-sm" role="group">
                                                            <button 
                                                                type="button" 
                                                                className="btn btn-success"
                                                                onClick={() => handleConfirmTransfer(transfer)}
                                                                title="Accept Transfer"
                                                            >
                                                                <i className="fas fa-check mr-1"></i>
                                                                Accept
                                                            </button>
                                                            <button 
                                                                type="button" 
                                                                className="btn btn-danger"
                                                                onClick={() => handleRejectTransfer(transfer)}
                                                                title="Reject Transfer"
                                                            >
                                                                <i className="fas fa-times mr-1"></i>
                                                                Reject
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Transactions Table */}
            <div className="row">
                <div className="col-12">
                    <div className="card">
                        <div className="card-header d-flex justify-content-between align-items-center">
                            <h5 className="mb-0">Transaction History</h5>
                            <span className="badge badge-info">{orders.length} transactions</span>
                        </div>
                        <div className="card-body p-0">
                            {orders.length === 0 ? (
                                <div className="text-center py-5">
                                    <i className="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                    <h5 className="text-muted">No transactions found</h5>
                                    <p className="text-muted">Start by making your first transaction</p>
                                </div>
                            ) : (
                                <div className="table-responsive">
                                    <table className="table table-hover mb-0">
                                        <thead className="thead-light">
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {orders.map((order) => (
                                                <tr key={order.id}>
                                                    <td className="font-weight-bold">#{order.id}</td>
                                                    <td>
                                                        <div className="text-truncate" style={{ maxWidth: '200px' }}>
                                                            {order.title}
                                                        </div>
                                                        {order.description && (
                                                            <small className="text-muted d-block text-truncate" style={{ maxWidth: '200px' }}>
                                                                {order.description}
                                                            </small>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <span className="badge badge-light">
                                                            {getOrderTypeDisplay(order.orderType)}
                                                        </span>
                                                    </td>
                                                    <td className="font-weight-bold">
                                                        {formatAmount(order.amount)}
                                                    </td>
                                                    <td>
                                                        <span className={`badge ${getStatusBadgeClass(order.status)}`}>
                                                            {order.status.replace('_', ' ').toUpperCase()}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            {formatDate(order.createdAt)}
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div className="btn-group btn-group-sm" role="group">
                                                            <button 
                                                                type="button" 
                                                                className="btn btn-outline-primary"
                                                                onClick={() => handleViewTransaction(order.id)}
                                                                title="View Details"
                                                            >
                                                                <i className="fas fa-eye"></i>
                                                            </button>
                                                            {(order.status === 'pending_payment' || order.status === 'pending_approval') && (
                                                                <button 
                                                                    type="button" 
                                                                    className="btn btn-outline-danger"
                                                                    onClick={() => handleCancelOrder(order)}
                                                                    title="Cancel Order"
                                                                >
                                                                    <i className="fas fa-times"></i>
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
});

WalletPage.displayName = 'WalletPage';

export default WalletPage;