import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { OrdersApi, Order } from '../../generated/src';
import { apiConfig } from '../../config/api';

const TransactionViewPage: React.FC = () => {
    const { orderId } = useParams<{ orderId: string }>();
    const navigate = useNavigate();
    const [order, setOrder] = useState<Order | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [canceling, setCanceling] = useState<boolean>(false);

    useEffect(() => {
        if (orderId) {
            loadOrder();
        }
    }, [orderId]);

    const loadOrder = async () => {
        try {
            setLoading(true);
            setError('');
            
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.getMerchantOrder({
                order: parseInt(orderId!)
            });
            
            setOrder(response);
        } catch (err: any) {
            setError(err.message || 'Failed to load transaction details');
        } finally {
            setLoading(false);
        }
    };

    const handleCancelOrder = async () => {
        if (!order) return;

        const confirmed = window.confirm(
            `Are you sure you want to cancel this ${getOrderTypeDisplay(order.orderType)}?\n\n` +
            `Order ID: #${order.id}\n` +
            `Amount: ${formatAmount(order.amount)}\n` +
            `Title: ${order.title}\n\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            setCanceling(true);
            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.cancelMerchantOrder({
                order: order.id
            });
            
            setOrder(response);
            
            // Show success message
            alert('Order has been successfully cancelled.');
            
        } catch (err: any) {
            alert(`Failed to cancel order: ${err.message || 'Unknown error'}`);
        } finally {
            setCanceling(false);
        }
    };

    const handleGoBack = () => {
        navigate('/wallet');
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
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
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

    const getOrderTypeIcon = (orderType: string): string => {
        switch (orderType) {
            case 'internal_transfer':
                return 'fas fa-exchange-alt';
            case 'user_top_up':
            case 'admin_top_up':
                return 'fas fa-plus-circle';
            case 'user_withdrawal':
            case 'admin_withdrawal':
                return 'fas fa-minus-circle';
            default:
                return 'fas fa-file-invoice';
        }
    };

    const canCancelOrder = (order: Order): boolean => {
        return order.status === 'pending_payment' || order.status === 'pending_approval';
    };

    if (loading) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12">
                        <div className="text-center">
                            <div className="spinner-border" role="status">
                                <span className="sr-only">Loading...</span>
                            </div>
                            <p className="mt-2">Loading transaction details...</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12 col-md-8 col-lg-6">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h5>Error Loading Transaction</h5>
                                <p className="text-muted">{error}</p>
                                <button 
                                    type="button" 
                                    className="btn btn-primary"
                                    onClick={handleGoBack}
                                >
                                    <i className="fas fa-arrow-left mr-2"></i>
                                    Back to Wallet
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (!order) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12 col-md-8 col-lg-6">
                        <div className="card">
                            <div className="card-body text-center">
                                <i className="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h5>Transaction Not Found</h5>
                                <p className="text-muted">The requested transaction could not be found.</p>
                                <button 
                                    type="button" 
                                    className="btn btn-primary"
                                    onClick={handleGoBack}
                                >
                                    <i className="fas fa-arrow-left mr-2"></i>
                                    Back to Wallet
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="container-fluid py-4">
            <div className="row justify-content-center">
                <div className="col-12 col-lg-8 col-xl-6">
                    {/* Header */}
                    <div className="d-flex align-items-center mb-4">
                        <button 
                            type="button" 
                            className="btn btn-link p-0 mr-3"
                            onClick={handleGoBack}
                        >
                            <i className="fas fa-arrow-left fa-lg"></i>
                        </button>
                        <div className="flex-grow-1">
                            <h2 className="mb-0">Transaction Details</h2>
                            <p className="text-muted mb-0">Order #{order.id}</p>
                        </div>
                        <div className="ml-3">
                            <span className={`badge badge-lg ${getStatusBadgeClass(order.status)}`}>
                                {order.status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                    </div>

                    {/* Main Transaction Card */}
                    <div className="card mb-4">
                        <div className="card-header bg-light">
                            <div className="d-flex align-items-center">
                                <i className={`${getOrderTypeIcon(order.orderType)} fa-2x text-primary mr-3`}></i>
                                <div>
                                    <h5 className="mb-0">{order.title}</h5>
                                    <small className="text-muted">{getOrderTypeDisplay(order.orderType)}</small>
                                </div>
                            </div>
                        </div>
                        <div className="card-body">
                            <div className="row">
                                <div className="col-12 col-md-6 mb-3">
                                    <label className="form-label text-muted">Amount</label>
                                    <div className="h4 text-primary mb-0">{formatAmount(order.amount)}</div>
                                </div>
                                <div className="col-12 col-md-6 mb-3">
                                    <label className="form-label text-muted">Status</label>
                                    <div>
                                        <span className={`badge ${getStatusBadgeClass(order.status)}`}>
                                            {order.status.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                                <div className="col-12 col-md-6 mb-3">
                                    <label className="form-label text-muted">Order Type</label>
                                    <div>{getOrderTypeDisplay(order.orderType)}</div>
                                </div>
                                <div className="col-12 col-md-6 mb-3">
                                    <label className="form-label text-muted">Created</label>
                                    <div>{formatDate(order.createdAt)}</div>
                                </div>
                                {order.status === 'completed' && order.updatedAt && (
                                    <div className="col-12 col-md-6 mb-3">
                                        <label className="form-label text-muted">Completed</label>
                                        <div>{formatDate(order.updatedAt)}</div>
                                    </div>
                                )}
                                {order.description && (
                                    <div className="col-12 mb-3">
                                        <label className="form-label text-muted">Description</label>
                                        <div className="p-2 bg-light rounded">{order.description}</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Additional Details */}
                    {(order.receiver || order.topUpProvider || order.providerReference) && (
                        <div className="card mb-4">
                            <div className="card-header">
                                <h6 className="mb-0">Additional Details</h6>
                            </div>
                            <div className="card-body">
                                <div className="row">
                                    {order.receiver && (
                                        <div className="col-12 col-md-6 mb-3">
                                            <label className="form-label text-muted">Recipient</label>
                                            <div>
                                                <strong>{order.receiver.name}</strong>
                                                <br />
                                                <small className="text-muted">{order.receiver.email}</small>
                                            </div>
                                        </div>
                                    )}
                                    {order.topUpProvider && (
                                        <div className="col-12 col-md-6 mb-3">
                                            <label className="form-label text-muted">Payment Method</label>
                                            <div>
                                                <strong>{order.topUpProvider.name}</strong>
                                                <br />
                                                <small className="text-muted">Code: {order.topUpProvider.code}</small>
                                            </div>
                                        </div>
                                    )}
                                    {order.providerReference && (
                                        <div className="col-12 mb-3">
                                            <label className="form-label text-muted">Provider Reference</label>
                                            <div className="p-2 bg-light rounded font-monospace">
                                                {order.providerReference}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Action Buttons */}
                    <div className="card">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <button 
                                    type="button" 
                                    className="btn btn-secondary"
                                    onClick={handleGoBack}
                                >
                                    <i className="fas fa-arrow-left mr-2"></i>
                                    Back to Wallet
                                </button>
                                
                                {canCancelOrder(order) && (
                                    <button 
                                        type="button" 
                                        className="btn btn-danger"
                                        onClick={handleCancelOrder}
                                        disabled={canceling}
                                    >
                                        {canceling ? (
                                            <>
                                                <span className="spinner-border spinner-border-sm mr-2" role="status"></span>
                                                Cancelling...
                                            </>
                                        ) : (
                                            <>
                                                <i className="fas fa-times mr-2"></i>
                                                Cancel Order
                                            </>
                                        )}
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Status Information */}
                    <div className="card mt-4">
                        <div className="card-body">
                            <h6 className="card-title">
                                <i className="fas fa-info-circle mr-2"></i>
                                Status Information
                            </h6>
                            <div className="text-muted">
                                {order.status === 'pending_payment' && (
                                    <p className="mb-0">This order is waiting for payment confirmation. You can cancel it if needed.</p>
                                )}
                                {order.status === 'pending_approval' && (
                                    <p className="mb-0">This order is pending administrative approval. You can still cancel it if needed.</p>
                                )}
                                {order.status === 'completed' && (
                                    <p className="mb-0">This order has been successfully completed and processed.</p>
                                )}
                                {order.status === 'cancelled' && (
                                    <p className="mb-0">This order has been cancelled and will not be processed.</p>
                                )}
                                {order.status === 'refunded' && (
                                    <p className="mb-0">This order has been refunded and the amount has been returned to your wallet.</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TransactionViewPage;