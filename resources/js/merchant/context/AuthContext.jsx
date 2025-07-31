import React, { createContext, useContext, useEffect, useState } from 'react';
import { MerchantAuthenticationApi } from '../generated';
import { apiConfig } from '../config/api';

const AuthContext = createContext();

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};

export const AuthProvider = ({ children }) => {
    const [isAuthenticated, setIsAuthenticated] = useState(false);
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    
    // Initialize auth API
    const authApi = new MerchantAuthenticationApi(apiConfig.getConfiguration());
    
    useEffect(() => {
        // Check authentication on mount
        const checkAuth = async () => {
            try {
                setLoading(true);
                
                if (apiConfig.isAuthenticated()) {
                    const response = await authApi.getMerchantUser();
                    if (response.success && response.user) {
                        setUser(response.user);
                        setIsAuthenticated(true);
                    } else {
                        apiConfig.setToken(null);
                        setIsAuthenticated(false);
                        setUser(null);
                    }
                } else {
                    setIsAuthenticated(false);
                    setUser(null);
                }
            } catch (err) {
                console.error('Auth check failed:', err);
                apiConfig.setToken(null);
                setIsAuthenticated(false);
                setUser(null);
            } finally {
                setLoading(false);
            }
        };
        
        checkAuth();
    }, []);
    
    const login = async (credentials) => {
        try {
            setLoading(true);
            setError(null);
            
            const response = await authApi.merchantLogin({
                loginRequest: credentials
            });
            
            if (response.success && response.token) {
                apiConfig.setToken(response.token);
                setUser(response.user);
                setIsAuthenticated(true);
                return { success: true, user: response.user };
            } else {
                throw new Error(response.message || 'Login failed');
            }
        } catch (err) {
            const errorMessage = err.message || 'Login failed';
            setError(errorMessage);
            setIsAuthenticated(false);
            setUser(null);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    };
    
    const logout = async () => {
        try {
            setLoading(true);
            await authApi.merchantLogout();
        } catch (err) {
            console.error('Logout error:', err);
        } finally {
            apiConfig.setToken(null);
            setIsAuthenticated(false);
            setUser(null);
            setLoading(false);
        }
    };
    
    const value = {
        isAuthenticated,
        user,
        loading,
        error,
        login,
        logout,
        clearError: () => setError(null)
    };
    
    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};