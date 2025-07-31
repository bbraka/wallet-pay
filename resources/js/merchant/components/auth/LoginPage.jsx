import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';

const LoginPage = () => {
    const { login, loading, error, isAuthenticated, clearError } = useAuth();
    const navigate = useNavigate();
    const [formData, setFormData] = useState({
        email: '',
        password: '',
        remember: false
    });
    const [formErrors, setFormErrors] = useState({});
    
    // Redirect if already authenticated
    useEffect(() => {
        if (isAuthenticated) {
            navigate('/wallet');
        }
    }, [isAuthenticated, navigate]);
    
    // Clear errors when component mounts
    useEffect(() => {
        clearError();
    }, [clearError]);
    
    const handleInputChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
        
        // Clear field error when user starts typing
        if (formErrors[name]) {
            setFormErrors(prev => ({
                ...prev,
                [name]: ''
            }));
        }
    };
    
    const validateForm = () => {
        const errors = {};
        
        if (!formData.email.trim()) {
            errors.email = 'Email is required';
        } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
            errors.email = 'Please enter a valid email address';
        }
        
        if (!formData.password.trim()) {
            errors.password = 'Password is required';
        } else if (formData.password.length < 6) {
            errors.password = 'Password must be at least 6 characters';
        }
        
        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        try {
            await login({
                email: formData.email,
                password: formData.password,
                remember: formData.remember
            });
            // Navigation will happen via the useEffect hook
        } catch (err) {
            // Error is handled by the AuthContext
            console.error('Login failed:', err);
        }
    };
    
    return (
        <div className="min-vh-100 d-flex align-items-center bg-light">
            <div className="container">
                <div className="row justify-content-center">
                    <div className="col-md-6 col-lg-4">
                        <div className="card shadow">
                            <div className="card-body p-5">
                                <div className="text-center mb-4">
                                    <h1 className="h3 mb-3 font-weight-normal">
                                        User Wallet
                                    </h1>
                                    <p className="text-muted">
                                        Sign in to your merchant account
                                    </p>
                                </div>
                                
                                {error && (
                                    <div className="alert alert-danger" role="alert">
                                        {error}
                                    </div>
                                )}
                                
                                <form onSubmit={handleSubmit}>
                                    <div className="form-group mb-3">
                                        <label htmlFor="email" className="form-label">
                                            Email Address
                                        </label>
                                        <input
                                            type="email"
                                            className={`form-control ${formErrors.email ? 'is-invalid' : ''}`}
                                            id="email"
                                            name="email"
                                            value={formData.email}
                                            onChange={handleInputChange}
                                            placeholder="Enter your email"
                                            disabled={loading}
                                            autoComplete="email"
                                            required
                                        />
                                        {formErrors.email && (
                                            <div className="invalid-feedback">
                                                {formErrors.email}
                                            </div>
                                        )}
                                    </div>
                                    
                                    <div className="form-group mb-3">
                                        <label htmlFor="password" className="form-label">
                                            Password
                                        </label>
                                        <input
                                            type="password"
                                            className={`form-control ${formErrors.password ? 'is-invalid' : ''}`}
                                            id="password"
                                            name="password"
                                            value={formData.password}
                                            onChange={handleInputChange}
                                            placeholder="Enter your password"
                                            disabled={loading}
                                            autoComplete="current-password"
                                            required
                                        />
                                        {formErrors.password && (
                                            <div className="invalid-feedback">
                                                {formErrors.password}
                                            </div>
                                        )}
                                    </div>
                                    
                                    <div className="form-group mb-4">
                                        <div className="form-check">
                                            <input
                                                type="checkbox"
                                                className="form-check-input"
                                                id="remember"
                                                name="remember"
                                                checked={formData.remember}
                                                onChange={handleInputChange}
                                                disabled={loading}
                                            />
                                            <label className="form-check-label" htmlFor="remember">
                                                Remember me
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button
                                        type="submit"
                                        className="btn btn-primary btn-lg btn-block w-100"
                                        disabled={loading}
                                    >
                                        {loading ? (
                                            <>
                                                <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                Signing in...
                                            </>
                                        ) : (
                                            'Sign In'
                                        )}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default LoginPage;