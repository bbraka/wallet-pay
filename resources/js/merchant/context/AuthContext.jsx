import React, { createContext, useContext, useEffect, useState } from 'react';
import authService from '../services/authService';

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
    
    useEffect(() => {
        // Initialize auth service and check authentication
        const initAuth = async () => {
            try {
                setLoading(true);
                await authService.init();
            } catch (err) {
                console.error('Auth initialization failed:', err);
                setError('Authentication initialization failed');
            } finally {
                setLoading(false);
            }
        };
        
        initAuth();
        
        // Listen for auth state changes
        const unsubscribe = authService.addAuthListener(({ isAuthenticated, user }) => {
            setIsAuthenticated(isAuthenticated);
            setUser(user);
            setError(null);
        });
        
        return unsubscribe;
    }, []);
    
    const login = async (credentials) => {
        try {
            setLoading(true);
            setError(null);
            const result = await authService.login(credentials);
            return result;
        } catch (err) {
            const errorMessage = err.data?.message || err.message || 'Login failed';
            setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    };
    
    const logout = async () => {
        try {
            setLoading(true);
            await authService.logout();
        } catch (err) {
            console.error('Logout error:', err);
        } finally {
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