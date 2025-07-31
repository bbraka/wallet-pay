import { Configuration } from '../generated/src';

/**
 * API Configuration utility for generated OpenAPI clients
 */
class ApiConfig {
    constructor() {
        this.baseURL = import.meta.env.VITE_API_URL || window.location.origin;
        this.token = localStorage.getItem('auth_token');
    }

    /**
     * Get configuration for API clients
     */
    getConfiguration() {
        return new Configuration({
            basePath: this.baseURL,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            accessToken: this.token ? async () => this.token : undefined
        });
    }

    /**
     * Update auth token
     */
    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('auth_token', token);
        } else {
            localStorage.removeItem('auth_token');
        }
    }

    /**
     * Get current token
     */
    getToken() {
        return this.token;
    }

    /**
     * Check if authenticated
     */
    isAuthenticated() {
        return !!this.token;
    }
}

// Export singleton instance
export const apiConfig = new ApiConfig();