import React from 'react';
import Header from './Header';
import { useAuth } from '../../context/AuthContext';

const Layout = ({ children, onRefreshWallet }) => {
    const { loading } = useAuth();
    
    if (loading) {
        return (
            <div className="d-flex justify-content-center align-items-center min-vh-100">
                <div className="spinner-border text-primary" role="status">
                    <span className="sr-only">Loading...</span>
                </div>
            </div>
        );
    }
    
    return (
        <div className="min-vh-100 bg-light">
            <Header onRefreshWallet={onRefreshWallet} />
            <main className="container-fluid py-4">
                {children}
            </main>
        </div>
    );
};

export default Layout;