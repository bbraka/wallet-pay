import React, { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { MerchantAuthenticationApi, User, LoginRequest } from '../generated/src';
import { apiConfig } from '../config/api';

interface AuthContextType {
    isAuthenticated: boolean;
    user: User | null;
    loading: boolean;
    error: string | null;
    login: (credentials: LoginRequest) => Promise<{ success: boolean; user: User }>;
    logout: () => Promise<void>;
    clearError: () => void;
}

interface AuthProviderProps {
    children: ReactNode;
}

interface LoginResponse {
    success: boolean;
    user: User;
    token?: string;
    message?: string;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const useAuth = (): AuthContextType => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
    const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    
    // Function to get fresh auth API instance
    const getAuthApi = (): MerchantAuthenticationApi => new MerchantAuthenticationApi(apiConfig.getConfiguration());
    
    useEffect(() => {
        // Check authentication on mount
        const checkAuth = async (): Promise<void> => {
            try {
                setLoading(true);
                
                if (apiConfig.isAuthenticated()) {
                    try {
                        // Try to get fresh user data from API
                        const response = await getAuthApi().getMerchantUser();
                        if (response.success && response.user) {
                            // Update localStorage with fresh data
                            localStorage.setItem('auth_user', JSON.stringify(response.user));
                            setUser(response.user);
                            setIsAuthenticated(true);
                        } else {
                            throw new Error('API returned unsuccessful response');
                        }
                    } catch (apiErr) {
                        console.error('API call failed, trying localStorage:', apiErr);
                        // If API call fails, try to load user from localStorage
                        const storedUser = localStorage.getItem('auth_user');
                        if (storedUser) {
                            try {
                                const parsedUser: User = JSON.parse(storedUser);
                                setUser(parsedUser);
                                setIsAuthenticated(true);
                            } catch (parseErr) {
                                console.error('Failed to parse stored user data:', parseErr);
                                // Clear invalid data
                                localStorage.removeItem('auth_user');
                                apiConfig.setToken(null);
                                setIsAuthenticated(false);
                                setUser(null);
                            }
                        } else {
                            // No stored user data, clear authentication
                            apiConfig.setToken(null);
                            setIsAuthenticated(false);
                            setUser(null);
                        }
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
    
    const login = async (credentials: LoginRequest): Promise<{ success: boolean; user: User }> => {
        try {
            setLoading(true);
            setError(null);
            
            const response = await getAuthApi().merchantLogin({
                loginRequest: credentials
            }) as LoginResponse;
            
            console.log('Login response:', response);
            
            if (response.success) {
                // The token field is not in the TypeScript interface but exists in the actual response
                const token = response.token;
                if (token) {
                    apiConfig.setToken(token);
                    // Store user data in localStorage
                    localStorage.setItem('auth_user', JSON.stringify(response.user));
                    setUser(response.user);
                    setIsAuthenticated(true);
                    return { success: true, user: response.user };
                } else {
                    throw new Error('No token received from server');
                }
            } else {
                throw new Error(response.message || 'Login failed');
            }
        } catch (err: any) {
            const errorMessage = err.message || 'Login failed';
            setError(errorMessage);
            setIsAuthenticated(false);
            setUser(null);
            throw new Error(errorMessage);
        } finally {
            setLoading(false);
        }
    };
    
    const logout = async (): Promise<void> => {
        try {
            setLoading(true);
            await getAuthApi().merchantLogout();
        } catch (err) {
            console.error('Logout error:', err);
        } finally {
            apiConfig.setToken(null);
            localStorage.removeItem('auth_user');
            setIsAuthenticated(false);
            setUser(null);
            setLoading(false);
        }
    };
    
    const value: AuthContextType = {
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