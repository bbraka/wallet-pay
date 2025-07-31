import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import LoginPage from './components/auth/LoginPage';
import ProtectedRoute from './components/auth/ProtectedRoute';
import Layout from './components/layout/Layout';
import WalletPage from './components/wallet/WalletPage';
import TopUpPage from './components/transactions/TopUpPage';
import TransferPage from './components/transactions/TransferPage';
import WithdrawalPage from './components/transactions/WithdrawalPage';

function MerchantApp() {
    return (
        <AuthProvider>
            <Router basename="/merchant">
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/wallet" element={
                        <ProtectedRoute>
                            <Layout>
                                <WalletPage />
                            </Layout>
                        </ProtectedRoute>
                    } />
                    <Route path="/top-up" element={
                        <ProtectedRoute>
                            <Layout>
                                <TopUpPage />
                            </Layout>
                        </ProtectedRoute>
                    } />
                    <Route path="/transfer" element={
                        <ProtectedRoute>
                            <Layout>
                                <TransferPage />
                            </Layout>
                        </ProtectedRoute>
                    } />
                    <Route path="/withdrawal" element={
                        <ProtectedRoute>
                            <Layout>
                                <WithdrawalPage />
                            </Layout>
                        </ProtectedRoute>
                    } />
                    <Route path="/" element={<Navigate to="/wallet" replace />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
}

const container = document.getElementById('merchant-app');
if (container) {
    const root = createRoot(container);
    root.render(<MerchantApp />);
}