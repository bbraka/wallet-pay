import React, { useState, FormEvent } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { 
    OrdersApi,
    CreateMerchantWithdrawalRequest 
} from '../../generated/src';
import { apiConfig } from '../../config/api';

interface FormData {
    amount: string;
    description: string;
}

const WithdrawalPage: React.FC = () => {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [success, setSuccess] = useState<string>('');
    
    const [formData, setFormData] = useState<FormData>({
        amount: '',
        description: ''
    });

    const handleInputChange = (field: keyof FormData, value: string) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
        
        // Clear messages when user starts typing
        if (error) setError('');
        if (success) setSuccess('');
    };

    const withdrawalAmount = parseFloat(formData.amount) || 0;
    const currentBalance = parseFloat(user?.walletAmount || 0);
    const balanceAfterWithdrawal = currentBalance - withdrawalAmount;

    const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        
        if (!formData.amount) {
            setError('Please enter withdrawal amount');
            return;
        }

        if (withdrawalAmount <= 0) {
            setError('Withdrawal amount must be greater than 0');
            return;
        }

        if (withdrawalAmount > currentBalance) {
            setError('Insufficient balance for this withdrawal');
            return;
        }

        try {
            setLoading(true);
            setError('');
            
            const withdrawalData: CreateMerchantWithdrawalRequest = {
                amount: withdrawalAmount,
                description: formData.description || undefined
            };

            const ordersApi = new OrdersApi(apiConfig.getConfiguration());
            const response = await ordersApi.createMerchantWithdrawal({
                createMerchantWithdrawalRequest: withdrawalData
            });
            
            setSuccess(`Withdrawal request created successfully! Order ID: #${response.id}`);
            
            // Reset form
            setFormData({
                amount: '',
                description: ''
            });
            
            // Show success message briefly then redirect to wallet
            setTimeout(() => {
                navigate('/wallet');
            }, 2000);
            
        } catch (err) {
            setError(err.message || 'Failed to create withdrawal request');
        } finally {
            setLoading(false);
        }
    };

    const handleGoBack = () => {
        navigate('/wallet');
    };

    // Quick amount buttons
    const quickAmounts = [10, 25, 50, 100, 250, 500];
    const availableQuickAmounts = quickAmounts.filter(amount => amount <= currentBalance);

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
                            <h2 className="mb-0">Withdraw Funds</h2>
                            <p className="text-muted mb-0">Request withdrawal from your wallet</p>
                        </div>
                    </div>

                    {/* Balance Cards */}
                    <div className="row mb-4">
                        <div className="col-6">
                            <div className="card bg-light">
                                <div className="card-body text-center">
                                    <h6 className="card-title mb-1 text-muted">Available Balance</h6>
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
                                    <h6 className="card-title mb-1 text-muted">After Withdrawal</h6>
                                    <h5 className={`mb-0 ${balanceAfterWithdrawal < 0 ? 'text-danger' : 'text-success'}`}>
                                        {new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(balanceAfterWithdrawal)}
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

                    {/* Low Balance Warning */}
                    {currentBalance <= 0 && (
                        <div className="alert alert-warning" role="alert">
                            <i className="fas fa-exclamation-circle mr-2"></i>
                            You have insufficient balance for withdrawal. Please add funds to your wallet first.
                        </div>
                    )}

                    {/* Withdrawal Form */}
                    <div className="card">
                        <div className="card-header">
                            <h5 className="mb-0">
                                <i className="fas fa-minus-circle mr-2"></i>
                                Create Withdrawal Request
                            </h5>
                        </div>
                        <div className="card-body">
                            {currentBalance > 0 ? (
                                <form onSubmit={handleSubmit}>
                                    {/* Quick Amount Buttons */}
                                    {availableQuickAmounts.length > 0 && (
                                        <div className="mb-4">
                                            <label className="form-label">Quick Amounts</label>
                                            <div className="d-flex flex-wrap gap-2">
                                                {availableQuickAmounts.map(amount => (
                                                    <button
                                                        key={amount}
                                                        type="button"
                                                        className={`btn btn-sm ${formData.amount === amount.toString() ? 'btn-warning' : 'btn-outline-warning'}`}
                                                        onClick={() => handleInputChange('amount', amount.toString())}
                                                        disabled={loading}
                                                    >
                                                        ${amount}
                                                    </button>
                                                ))}
                                                {currentBalance > 0 && (
                                                    <button
                                                        type="button"
                                                        className={`btn btn-sm ${formData.amount === currentBalance.toString() ? 'btn-warning' : 'btn-outline-warning'}`}
                                                        onClick={() => handleInputChange('amount', currentBalance.toString())}
                                                        disabled={loading}
                                                    >
                                                        All (${currentBalance.toFixed(2)})
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <div className="mb-3">
                                        <label htmlFor="amount" className="form-label">
                                            Withdrawal Amount <span className="text-danger">*</span>
                                        </label>
                                        <div className="input-group">
                                            <span className="input-group-text">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max={currentBalance}
                                                className={`form-control ${withdrawalAmount > currentBalance ? 'is-invalid' : ''}`}
                                                id="amount"
                                                placeholder="0.00"
                                                value={formData.amount}
                                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleInputChange('amount', e.target.value)}
                                                required
                                                disabled={loading}
                                            />
                                        </div>
                                        {withdrawalAmount > 0 && (
                                            <small className={`form-text ${withdrawalAmount > currentBalance ? 'text-danger' : 'text-muted'}`}>
                                                {withdrawalAmount > currentBalance 
                                                    ? 'Insufficient balance for this withdrawal'
                                                    : `Maximum withdrawal: ${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(currentBalance)}`
                                                }
                                            </small>
                                        )}
                                    </div>

                                    <div className="mb-4">
                                        <label htmlFor="description" className="form-label">
                                            Reason for Withdrawal <span className="text-muted">(Optional)</span>
                                        </label>
                                        <textarea
                                            className="form-control"
                                            id="description"
                                            rows="3"
                                            placeholder="Describe the reason for this withdrawal request..."
                                            value={formData.description}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => handleInputChange('description', e.target.value)}
                                            disabled={loading}
                                        ></textarea>
                                    </div>

                                    {/* Withdrawal Summary */}
                                    {withdrawalAmount > 0 && (
                                        <div className="card bg-light mb-4">
                                            <div className="card-body">
                                                <h6 className="card-title">Withdrawal Summary</h6>
                                                <div className="d-flex justify-content-between mb-2">
                                                    <span>Current Balance:</span>
                                                    <strong>
                                                        {new Intl.NumberFormat('en-US', {
                                                            style: 'currency',
                                                            currency: 'USD'
                                                        }).format(currentBalance)}
                                                    </strong>
                                                </div>
                                                <div className="d-flex justify-content-between mb-2">
                                                    <span>Withdrawal Amount:</span>
                                                    <strong className="text-warning">
                                                        -{new Intl.NumberFormat('en-US', {
                                                            style: 'currency',
                                                            currency: 'USD'
                                                        }).format(withdrawalAmount)}
                                                    </strong>
                                                </div>
                                                <hr />
                                                <div className="d-flex justify-content-between">
                                                    <span>Remaining Balance:</span>
                                                    <strong className={balanceAfterWithdrawal < 0 ? 'text-danger' : 'text-success'}>
                                                        {new Intl.NumberFormat('en-US', {
                                                            style: 'currency',
                                                            currency: 'USD'
                                                        }).format(balanceAfterWithdrawal)}
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
                                            className="btn btn-warning"
                                            disabled={loading || withdrawalAmount > currentBalance || withdrawalAmount <= 0}
                                        >
                                            {loading ? (
                                                <>
                                                    <span className="spinner-border spinner-border-sm mr-2" role="status"></span>
                                                    Creating Request...
                                                </>
                                            ) : (
                                                <>
                                                    <i className="fas fa-paper-plane mr-2"></i>
                                                    Submit Withdrawal Request
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-center py-4">
                                    <i className="fas fa-wallet fa-3x text-muted mb-3"></i>
                                    <h5 className="text-muted">Insufficient Balance</h5>
                                    <p className="text-muted">You need to add funds to your wallet before you can make a withdrawal.</p>
                                    <button 
                                        type="button" 
                                        className="btn btn-success"
                                        onClick={() => navigate('/top-up')}
                                    >
                                        <i className="fas fa-plus mr-2"></i>
                                        Add Funds
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Information Card */}
                    <div className="card mt-4">
                        <div className="card-body">
                            <h6 className="card-title">
                                <i className="fas fa-info-circle mr-2"></i>
                                Withdrawal Information
                            </h6>
                            <ul className="mb-0 text-muted">
                                <li>All withdrawal requests require manual approval by administrators</li>
                                <li>Processing time may vary depending on the withdrawal method</li>
                                <li>You will be notified via email once your withdrawal is processed</li>
                                <li>Withdrawal history can be viewed in your transaction list</li>
                                <li>Minimum withdrawal amount may apply based on your account settings</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default WithdrawalPage;