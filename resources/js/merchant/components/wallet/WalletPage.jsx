import React, { useState, useEffect, forwardRef, useImperativeHandle } from 'react';
import { useAuth } from '../../context/AuthContext';
import { OrdersApi, MerchantAuthenticationApi } from '../../generated/src';
import { apiConfig } from '../../config/api';

const WalletPage = forwardRef((props, ref) => {
    const { user } = useAuth();
    const [currentUser, setCurrentUser] = useState(user);
    const [orders, setOrders] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [filters, setFilters] = useState({
        date_from: '',
        date_to: '',
        status: '',
        min_amount: '',
        max_amount: ''
    });

    useEffect(() => {
        loadUserData();
        loadOrders();
    }, []);

    // Expose refresh function to parent component
    useImperativeHandle(ref, () => ({
        refreshData: () => {
            loadUserData();
            loadOrders();
        }
    }));

    const loadUserData = async () => {
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

    const loadOrders = async (filterParams = {}) => {
        try {
            setLoading(true);
            setError('');
            
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.getMerchantOrders({
                ...filters,
                ...filterParams
            });
            
            setOrders(response.data || []);
        } catch (err) {
            setError(err.message || 'Failed to load orders');
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (field, value) => {
        const newFilters = { ...filters, [field]: value };
        setFilters(newFilters);
    };

    const handleFilterSubmit = (e) => {
        e.preventDefault();
        loadOrders(filters);
    };

    const handleFilterReset = () => {
        const resetFilters = {
            date_from: '',
            date_to: '',
            status: '',
            min_amount: '',
            max_amount: ''
        };
        setFilters(resetFilters);
        loadOrders(resetFilters);
    };

    const formatAmount = (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getStatusBadgeClass = (status) => {
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

    const getOrderTypeDisplay = (orderType) => {
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
                                            onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-6 col-lg-3 mb-3">
                                        <label htmlFor="date_to" className="form-label">To Date</label>
                                        <input
                                            type="date"
                                            className="form-control"
                                            id="date_to"
                                            value={filters.date_to}
                                            onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                        />
                                    </div>
                                    <div className="col-md-6 col-lg-2 mb-3">
                                        <label htmlFor="status" className="form-label">Status</label>
                                        <select
                                            className="form-control"
                                            id="status"
                                            value={filters.status}
                                            onChange={(e) => handleFilterChange('status', e.target.value)}
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
                                            onChange={(e) => handleFilterChange('min_amount', e.target.value)}
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
                                            onChange={(e) => handleFilterChange('max_amount', e.target.value)}
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
                                                <th>ID</th>
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
                                                                onClick={() => {/* View details functionality */}}
                                                                title="View Details"
                                                            >
                                                                <i className="fas fa-eye"></i>
                                                            </button>
                                                            {(order.status === 'pending_payment' || order.status === 'pending_approval') && (
                                                                <button 
                                                                    type="button" 
                                                                    className="btn btn-outline-danger"
                                                                    onClick={() => {/* Cancel order functionality */}}
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