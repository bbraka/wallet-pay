import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { OrdersApi, TopUpProvidersApi } from '../../generated/src';
import { apiConfig } from '../../config/api';

const TopUpPage = () => {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [providers, setProviders] = useState([]);
    const [loading, setLoading] = useState(false);
    const [loadingProviders, setLoadingProviders] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    
    const [formData, setFormData] = useState({
        title: '',
        amount: '',
        description: '',
        top_up_provider_id: '',
        provider_reference: ''
    });

    useEffect(() => {
        loadProviders();
    }, []);

    const loadProviders = async () => {
        try {
            setLoadingProviders(true);
            const topUpApi = new TopUpProvidersApi(apiConfig.getConfiguration());
            const response = await topUpApi.getMerchantTopUpProviders();
            setProviders(response || []);
        } catch (err) {
            setError('Failed to load top-up providers');
        } finally {
            setLoadingProviders(false);
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

    const selectedProvider = providers.find(p => p.id === parseInt(formData.top_up_provider_id));

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!formData.title || !formData.amount || !formData.top_up_provider_id) {
            setError('Please fill in all required fields');
            return;
        }

        if (parseFloat(formData.amount) <= 0) {
            setError('Amount must be greater than 0');
            return;
        }

        if (selectedProvider?.requiresReference && !formData.provider_reference) {
            setError('Provider reference is required for this payment method');
            return;
        }

        try {
            setLoading(true);
            setError('');
            
            const orderData = {
                title: formData.title,
                amount: parseFloat(formData.amount),
                description: formData.description || null,
                top_up_provider_id: parseInt(formData.top_up_provider_id),
                provider_reference: formData.provider_reference || null
            };

            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.createMerchantOrder({
                createMerchantOrderRequest: orderData
            });
            
            setSuccess(`Top-up order created successfully! Order ID: #${response.id}`);
            
            // Reset form
            setFormData({
                title: '',
                amount: '',
                description: '',
                top_up_provider_id: '',
                provider_reference: ''
            });
            
            // Scroll to top to show success message
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
        } catch (err) {
            setError(err.message || 'Failed to create top-up order');
        } finally {
            setLoading(false);
        }
    };

    const handleGoBack = () => {
        navigate('/wallet');
    };

    if (loadingProviders) {
        return (
            <div className="container-fluid py-4">
                <div className="row justify-content-center">
                    <div className="col-12 col-md-8 col-lg-6">
                        <div className="text-center">
                            <div className="spinner-border" role="status">
                                <span className="sr-only">Loading...</span>
                            </div>
                            <p className="mt-2">Loading payment providers...</p>
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
                            <h2 className="mb-0">Top Up Wallet</h2>
                            <p className="text-muted mb-0">Add funds to your wallet</p>
                        </div>
                    </div>

                    {/* Current Balance Card */}
                    <div className="card bg-light mb-4">
                        <div className="card-body">
                            <div className="row align-items-center">
                                <div className="col">
                                    <h6 className="card-title mb-1 text-muted">Current Balance</h6>
                                    <h4 className="mb-0 text-primary">
                                        {new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(user?.walletAmount || 0)}
                                    </h4>
                                </div>
                                <div className="col-auto">
                                    <i className="fas fa-wallet fa-2x text-primary opacity-50"></i>
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

                    {/* Top-up Form */}
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-plus-circle mr-2"></i>
                                Create Top-up Order
                            </h5>
                        </div>
                        <div className="card-body">
                            <form onSubmit={handleSubmit}>
                                <div className="mb-3">
                                    <label htmlFor="title" className="form-label">
                                        Title <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        id="title"
                                        placeholder="e.g., Wallet Top-up via Bank Transfer"
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
                                            className="form-control"
                                            id="amount"
                                            placeholder="0.00"
                                            value={formData.amount}
                                            onChange={(e) => handleInputChange('amount', e.target.value)}
                                            required
                                            disabled={loading}
                                        />
                                    </div>
                                </div>

                                <div className="mb-3">
                                    <label htmlFor="top_up_provider_id" className="form-label">
                                        Payment Method <span className="text-danger">*</span>
                                    </label>
                                    <select
                                        className="form-control"
                                        id="top_up_provider_id"
                                        value={formData.top_up_provider_id}
                                        onChange={(e) => handleInputChange('top_up_provider_id', e.target.value)}
                                        required
                                        disabled={loading}
                                    >
                                        <option value="">Select payment method...</option>
                                        {providers.map(provider => (
                                            <option key={provider.id} value={provider.id}>
                                                {provider.name}
                                                {provider.description && ` - ${provider.description}`}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {selectedProvider?.requiresReference && (
                                    <div className="mb-3">
                                        <label htmlFor="provider_reference" className="form-label">
                                            Payment Reference <span className="text-danger">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            id="provider_reference"
                                            placeholder="Enter transaction reference, receipt number, etc."
                                            value={formData.provider_reference}
                                            onChange={(e) => handleInputChange('provider_reference', e.target.value)}
                                            required
                                            disabled={loading}
                                        />
                                        <small className="form-text text-muted">
                                            This payment method requires a reference number for verification.
                                        </small>
                                    </div>
                                )}

                                <div className="mb-4">
                                    <label htmlFor="description" className="form-label">
                                        Description <span className="text-muted">(Optional)</span>
                                    </label>
                                    <textarea
                                        className="form-control"
                                        id="description"
                                        rows="3"
                                        placeholder="Additional notes about this top-up..."
                                        value={formData.description}
                                        onChange={(e) => handleInputChange('description', e.target.value)}
                                        disabled={loading}
                                    ></textarea>
                                </div>

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
                                        className="btn btn-success"
                                        disabled={loading}
                                    >
                                        {loading ? (
                                            <>
                                                <span className="spinner-border spinner-border-sm mr-2" role="status"></span>
                                                Creating Order...
                                            </>
                                        ) : (
                                            <>
                                                <i className="fas fa-plus mr-2"></i>
                                                Create Top-up Order
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
                                Important Information
                            </h6>
                            <ul className="mb-0 text-muted">
                                <li>Top-up orders may require manual approval before funds are added to your wallet</li>
                                <li>Processing time varies by payment method</li>
                                <li>Make sure to provide accurate payment references when required</li>
                                <li>You can track the status of your order in the transaction history</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TopUpPage;