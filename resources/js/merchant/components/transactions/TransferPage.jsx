import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { OrdersApi, MerchantAuthenticationApi } from '../../generated/src';
import { apiConfig } from '../../config/api';

const TransferPage = () => {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [loadingUsers, setLoadingUsers] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    
    const [formData, setFormData] = useState({
        title: '',
        amount: '',
        description: '',
        receiver_user_id: ''
    });

    useEffect(() => {
        loadUsers();
    }, []);

    const loadUsers = async () => {
        try {
            setLoadingUsers(true);
            const authApi = new MerchantAuthenticationApi(apiConfig.getConfiguration());
            const response = await authApi.getMerchantUsers();
            // Filter out current user from the list
            const otherUsers = (response.users || []).filter(u => u.id !== user?.id);
            setUsers(otherUsers);
        } catch (err) {
            setError('Failed to load users list');
        } finally {
            setLoadingUsers(false);
        }
    };

    const handleInputChange = (field, value) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
        
        // Clear messages when user starts typing
        if (error) setError('');
        if (success) setSuccess('');
    };

    const selectedUser = users.find(u => u.id === parseInt(formData.receiver_user_id));
    const transferAmount = parseFloat(formData.amount) || 0;
    const currentBalance = parseFloat(user?.walletAmount || 0);
    const balanceAfterTransfer = currentBalance - transferAmount;

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!formData.title || !formData.amount || !formData.receiver_user_id) {
            setError('Please fill in all required fields');
            return;
        }

        if (transferAmount <= 0) {
            setError('Transfer amount must be greater than 0');
            return;
        }

        if (transferAmount > currentBalance) {
            setError('Insufficient balance for this transfer');
            return;
        }

        if (parseInt(formData.receiver_user_id) === user?.id) {
            setError('You cannot transfer to yourself');
            return;
        }

        try {
            setLoading(true);
            setError('');
            
            const orderData = {
                title: formData.title,
                amount: transferAmount,
                description: formData.description || null,
                receiver_user_id: parseInt(formData.receiver_user_id)
            };

            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.createMerchantOrder({
                createMerchantOrderRequest: orderData
            });
            
            setSuccess(`Transfer order created successfully! Order ID: #${response.id}`);
            
            // Reset form
            setFormData({
                title: '',
                amount: '',
                description: '',
                receiver_user_id: ''
            });
            
            // Scroll to top to show success message
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
        } catch (err) {
            setError(err.message || 'Failed to create transfer order');
        } finally {
            setLoading(false);
        }
    };

    const handleGoBack = () => {
        navigate('/wallet');
    };

    if (loadingUsers) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12 col-md-8 col-lg-6">
                        <div className="text-center">
                            <div className="spinner-border" role="status">
                                <span className="sr-only">Loading...</span>
                            </div>
                            <p className="mt-2">Loading users...</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="container-fluid py-4">
            <div className="row justify-content-center">
                <div className="col-12 col-md-8 col-lg-6">
                    {/* Header */}
                    <div className="d-flex align-items-center mb-4">
                        <button 
                            type="button" 
                            className="btn btn-link p-0 mr-3"
                            onClick={handleGoBack}
                        >
                            <i className="fas fa-arrow-left fa-lg"></i>
                        </button>
                        <div>
                            <h2 className="mb-0">Transfer Funds</h2>
                            <p className="text-muted mb-0">Send money to another user</p>
                        </div>
                    </div>

                    {/* Balance Cards */}
                    <div className="row mb-4">
                        <div className="col-6">
                            <div className="card bg-light">
                                <div className="card-body text-center">
                                    <h6 className="card-title mb-1 text-muted">Current Balance</h6>
                                    <h5 className="mb-0 text-primary">
                                        {new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(currentBalance)}
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <div className="col-6">
                            <div className="card bg-light">
                                <div className="card-body text-center">
                                    <h6 className="card-title mb-1 text-muted">After Transfer</h6>
                                    <h5 className={`mb-0 ${balanceAfterTransfer < 0 ? 'text-danger' : 'text-success'}`}>
                                        {new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(balanceAfterTransfer)}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="alert alert-danger" role="alert">
                            <i className="fas fa-exclamation-triangle mr-2"></i>
                            {error}
                        </div>
                    )}

                    {/* Success Message */}
                    {success && (
                        <div className="alert alert-success" role="alert">
                            <i className="fas fa-check-circle mr-2"></i>
                            {success}
                        </div>
                    )}

                    {/* Transfer Form */}
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-exchange-alt mr-2"></i>
                                Create Transfer Order
                            </h5>
                        </div>
                        <div className="card-body">
                            <form onSubmit={handleSubmit}>
                                <div className="mb-3">
                                    <label htmlFor="receiver_user_id" className="form-label">
                                        Recipient <span className="text-danger">*</span>
                                    </label>
                                    <select
                                        className="form-control"
                                        id="receiver_user_id"
                                        value={formData.receiver_user_id}
                                        onChange={(e) => handleInputChange('receiver_user_id', e.target.value)}
                                        required
                                        disabled={loading}
                                    >
                                        <option value="">Select recipient...</option>
                                        {users.map(user => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} ({user.email})
                                            </option>
                                        ))}
                                    </select>
                                    {selectedUser && (
                                        <div className="mt-2 p-2 bg-light rounded">
                                            <small className="text-muted">
                                                <strong>Selected:</strong> {selectedUser.name} ({selectedUser.email})
                                            </small>
                                        </div>
                                    )}
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="title" className="form-label">
                                        Title <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        id="title"
                                        placeholder="e.g., Payment for services"
                                        value={formData.title}
                                        onChange={(e) => handleInputChange('title', e.target.value)}
                                        required
                                        disabled={loading}
                                    />
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="amount" className="form-label">
                                        Amount <span className="text-danger">*</span>
                                    </label>
                                    <div className="input-group">
                                        <span className="input-group-text">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            max={currentBalance}
                                            className={`form-control ${transferAmount > currentBalance ? 'is-invalid' : ''}`}
                                            id="amount"
                                            placeholder="0.00"
                                            value={formData.amount}
                                            onChange={(e) => handleInputChange('amount', e.target.value)}
                                            required
                                            disabled={loading}
                                        />
                                    </div>
                                    {transferAmount > 0 && (
                                        <small className={`form-text ${transferAmount > currentBalance ? 'text-danger' : 'text-muted'}`}>
                                            {transferAmount > currentBalance 
                                                ? 'Insufficient balance for this transfer'
                                                : `Available balance: ${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(currentBalance)}`
                                            }
                                        </small>
                                    )}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="description" className="form-label">
                                        Description <span className="text-muted">(Optional)</span>
                                    </label>
                                    <textarea
                                        className="form-control"
                                        id="description"
                                        rows="3"
                                        placeholder="Purpose of this transfer..."
                                        value={formData.description}
                                        onChange={(e) => handleInputChange('description', e.target.value)}
                                        disabled={loading}
                                    ></textarea>
                                </div>

                                {/* Transfer Summary */}
                                {transferAmount > 0 && selectedUser && (
                                    <div className="card bg-light mb-4">
                                        <div className="card-body">
                                            <h6 className="card-title">Transfer Summary</h6>
                                            <div className="row">
                                                <div className="col-6">
                                                    <small className="text-muted">From:</small>
                                                    <br />
                                                    <strong>{user?.name}</strong>
                                                </div>
                                                <div className="col-6">
                                                    <small className="text-muted">To:</small>
                                                    <br />
                                                    <strong>{selectedUser.name}</strong>
                                                </div>
                                            </div>
                                            <hr />
                                            <div className="d-flex justify-content-between">
                                                <span>Transfer Amount:</span>
                                                <strong>
                                                    {new Intl.NumberFormat('en-US', {
                                                        style: 'currency',
                                                        currency: 'USD'
                                                    }).format(transferAmount)}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="d-flex justify-content-between">
                                    <button 
                                        type="button" 
                                        className="btn btn-secondary"
                                        onClick={handleGoBack}
                                        disabled={loading}
                                    >
                                        <i className="fas fa-arrow-left mr-2"></i>
                                        Back
                                    </button>
                                    <button 
                                        type="submit" 
                                        className="btn btn-info"
                                        disabled={loading || transferAmount > currentBalance}
                                    >
                                        {loading ? (
                                            <>
                                                <span className="spinner-border spinner-border-sm mr-2" role="status"></span>
                                                Processing Transfer...
                                            </>
                                        ) : (
                                            <>
                                                <i className="fas fa-paper-plane mr-2"></i>
                                                Send Transfer
                                            </>
                                        )}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Information Card */}
                    <div className="card mt-4">
                        <div className="card-body">
                            <h6 className="card-title">
                                <i className="fas fa-info-circle mr-2"></i>
                                Transfer Information
                            </h6>
                            <ul className="mb-0 text-muted">
                                <li>Transfers may require approval before being processed</li>
                                <li>You can only transfer funds you currently have in your wallet</li>
                                <li>Both parties will receive notifications about the transfer status</li>
                                <li>Transfer history can be viewed in your transaction list</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TransferPage;