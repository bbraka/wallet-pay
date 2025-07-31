import apiService from './apiService';

class AuthService {
    constructor() {
        this.currentUser = null;
        this.isAuthenticated = false;
        this.listeners = [];
    }
    
    // Check if user is currently authenticated
    getIsAuthenticated() {
        return this.isAuthenticated && apiService.isAuthenticated();
    }
    
    // Get current user data
    getCurrentUser() {
        return this.currentUser;
    }
    
    // Login user
    async login(credentials) {
        try {
            const response = await apiService.login(credentials);
            
            if (response.success) {
                this.currentUser = response.user;
                this.isAuthenticated = true;
                this.notifyListeners();
                return { success: true, user: response.user };
            } else {
                throw new Error(response.message || 'Login failed');
            }
        } catch (error) {
            this.isAuthenticated = false;
            this.currentUser = null;
            throw error;
        }
    }
    
    // Logout user
    async logout() {
        try {
            await apiService.logout();
        } catch (error) {
            console.warn('Logout API call failed:', error);
        } finally {
            // Always clear local auth state regardless of API response
            this.isAuthenticated = false;
            this.currentUser = null;
            this.notifyListeners();
        }
    }
    
    // Check authentication status and get user data
    async checkAuth() {
        try {
            const response = await apiService.getCurrentUser();
            
            if (response.success && response.user) {
                this.currentUser = response.user;
                this.isAuthenticated = true;
                this.notifyListeners();
                return true;
            } else {
                this.isAuthenticated = false;
                this.currentUser = null;
                this.notifyListeners();
                return false;
            }
        } catch (error) {
            this.isAuthenticated = false;
            this.currentUser = null;
            this.notifyListeners();
            return false;
        }
    }
    
    // Add listener for auth state changes
    addAuthListener(callback) {
        this.listeners.push(callback);
        
        // Return unsubscribe function
        return () => {
            this.listeners = this.listeners.filter(listener => listener !== callback);
        };
    }
    
    // Notify all listeners of auth state change
    notifyListeners() {
        this.listeners.forEach(callback => {
            callback({
                isAuthenticated: this.isAuthenticated,
                user: this.currentUser
            });
        });
    }
    
    // Get CSRF token from meta tag
    getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : null;
    }
    
    // Initialize auth service
    init() {
        // Check if user has a stored token
        if (apiService.isAuthenticated()) {
            // Try to get current user data to verify token is still valid
            return this.checkAuth();
        } else {
            // No token, user is not authenticated
            this.isAuthenticated = false;
            this.currentUser = null;
            this.notifyListeners();
            return Promise.resolve(false);
        }
    }
}

// Create and export singleton instance
const authService = new AuthService();
export default authService;