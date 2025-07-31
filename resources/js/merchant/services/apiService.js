import { Configuration, MerchantAuthenticationApi, OrdersApi, TopUpProvidersApi } from '../generated';

class ApiService {
    constructor() {
        // Use current origin if VITE_API_URL is not set, to work with different ports during testing
        this.baseURL = import.meta.env.VITE_API_URL || window.location.origin;
        console.log('API Service initialized with baseURL:', this.baseURL);
        this.token = localStorage.getItem('auth_token');
        
        this.configuration = new Configuration({
            basePath: this.baseURL,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            accessToken: this.token ? async () => this.token : undefined
        });
        
        // Initialize API instances
        this.authApi = new MerchantAuthenticationApi(this.configuration);
        this.ordersApi = new OrdersApi(this.configuration);
        this.topUpProvidersApi = new TopUpProvidersApi(this.configuration);
    }
    
    // Update configuration with new token
    setToken(token) {
        this.token = token;
        localStorage.setItem('auth_token', token);
        this.updateConfiguration();
    }
    
    // Remove token
    removeToken() {
        this.token = null;
        localStorage.removeItem('auth_token');
        this.updateConfiguration();
    }
    
    // Handle token from response headers
    handleTokenFromResponse(response) {
        if (response && response.headers) {
            const authHeader = response.headers.get('Authorization');
            const tokenHeader = response.headers.get('X-Auth-Token');
            
            if (tokenHeader) {
                this.setToken(tokenHeader);
            } else if (authHeader && authHeader.startsWith('Bearer ')) {
                const token = authHeader.substring(7);
                this.setToken(token);
            }
        }
    }
    
    // Check if user is authenticated
    isAuthenticated() {
        return !!this.token;
    }
    
    // Update configuration (for example, to add CSRF token)  
    updateConfiguration(config) {
        this.configuration = new Configuration({
            basePath: this.baseURL,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            accessToken: this.token ? async () => this.token : undefined,
            ...config
        });
        
        // Re-initialize API instances with new configuration
        this.authApi = new MerchantAuthenticationApi(this.configuration);
        this.ordersApi = new OrdersApi(this.configuration);
        this.topUpProvidersApi = new TopUpProvidersApi(this.configuration);
    }
    
    
    // Authentication methods
    async login(credentials) {
        try {
            // Direct fetch call to bypass OpenAPI client issues
            const response = await fetch(`${this.baseURL}/api/merchant/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(credentials)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Direct API Response:', JSON.stringify(data, null, 2));
            
            // Store the token from the response
            if (data.token) {
                console.log('Token found:', data.token);
                this.setToken(data.token);
            } else {
                console.log('No token in response. Response keys:', Object.keys(data));
            }
            
            return data;
        } catch (error) {
            console.error('Login error:', error);
            throw this.handleApiError(error);
        }
    }
    
    async logout() {
        try {
            const response = await this.authApi.merchantLogout();
            this.removeToken();
            return response;
        } catch (error) {
            this.removeToken(); // Remove token even if logout fails
            throw this.handleApiError(error);
        }
    }
    
    async getCurrentUser() {
        try {
            const response = await this.authApi.getMerchantUserRaw();
            this.handleTokenFromResponse(response.raw);
            return response.value();
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async getUsers() {
        try {
            const response = await this.authApi.getMerchantUsers();
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    // Orders methods
    async getOrders(filters = {}) {
        try {
            const response = await this.ordersApi.getMerchantOrdersRaw(filters);
            this.handleTokenFromResponse(response.raw);
            return response.value();
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async createOrder(orderData) {
        try {
            const response = await this.ordersApi.createMerchantOrderRaw({
                createMerchantOrderRequest: orderData
            });
            this.handleTokenFromResponse(response.raw);
            return response.value();
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async getOrder(orderId) {
        try {
            const response = await this.ordersApi.getMerchantOrder({ order: orderId });
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async updateOrder(orderId, orderData) {
        try {
            const response = await this.ordersApi.updateMerchantOrder({
                order: orderId,
                updateMerchantOrderRequest: orderData
            });
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async cancelOrder(orderId) {
        try {
            const response = await this.ordersApi.cancelMerchantOrder({ order: orderId });
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async createWithdrawal(withdrawalData) {
        try {
            const response = await this.ordersApi.createMerchantWithdrawal({
                createMerchantWithdrawalRequest: withdrawalData
            });
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    async getOrderRules() {
        try {
            const response = await this.ordersApi.getMerchantOrderRules();
            return response;
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    // Top-up providers methods
    async getTopUpProviders() {
        try {
            const response = await this.topUpProvidersApi.getMerchantTopUpProvidersRaw();
            this.handleTokenFromResponse(response.raw);
            return response.value();
        } catch (error) {
            throw this.handleApiError(error);
        }
    }
    
    // Error handling
    handleApiError(error) {
        if (error.response) {
            // API returned an error response
            return {
                status: error.response.status,
                message: error.response.statusText,
                data: error.response.data || null
            };
        } else if (error.request) {
            // Network error
            return {
                status: 0,
                message: 'Network error - please check your connection',
                data: null
            };
        } else {
            // Something else happened
            return {
                status: 500,
                message: error.message || 'An unexpected error occurred',
                data: null
            };
        }
    }
}

// Create and export a singleton instance
const apiService = new ApiService();
export default apiService;