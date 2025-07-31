import React, { useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';

const Header = () => {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [dropdownOpen, setDropdownOpen] = useState(false);
    
    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };
    
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount || 0);
    };
    
    return (
        <nav className="navbar navbar-expand-lg navbar-dark bg-primary">
            <div className="container-fluid">
                <a className="navbar-brand" href="#" onClick={(e) => { e.preventDefault(); navigate('/wallet'); }}>
                    <strong>User Wallet</strong>
                </a>
                
                <button
                    className="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span className="navbar-toggler-icon"></span>
                </button>
                
                <div className="collapse navbar-collapse" id="navbarNav">
                    <ul className="navbar-nav me-auto">
                        <li className="nav-item">
                            <a 
                                className="nav-link" 
                                href="#"
                                onClick={(e) => { e.preventDefault(); navigate('/wallet'); }}
                            >
                                Wallet
                            </a>
                        </li>
                        <li className="nav-item">
                            <a 
                                className="nav-link" 
                                href="#"
                                onClick={(e) => { e.preventDefault(); navigate('/top-up'); }}
                            >
                                Top Up
                            </a>
                        </li>
                        <li className="nav-item">
                            <a 
                                className="nav-link" 
                                href="#"
                                onClick={(e) => { e.preventDefault(); navigate('/transfer'); }}
                            >
                                Transfer
                            </a>
                        </li>
                        <li className="nav-item">
                            <a 
                                className="nav-link" 
                                href="#"
                                onClick={(e) => { e.preventDefault(); navigate('/withdrawal'); }}
                            >
                                Withdraw
                            </a>
                        </li>
                    </ul>
                    
                    {user && (
                        <div className="navbar-nav">
                            <div className="nav-item dropdown">
                                <a
                                    className="nav-link dropdown-toggle d-flex align-items-center"
                                    href="#"
                                    id="navbarDropdown"
                                    role="button"
                                    aria-expanded={dropdownOpen}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        setDropdownOpen(!dropdownOpen);
                                    }}
                                >
                                    <div className="me-2">
                                        <div className="fw-bold">{user.name}</div>
                                        <small className="text-light">
                                            Balance: {formatCurrency(user.wallet_amount)}
                                        </small>
                                    </div>
                                </a>
                                <ul className={`dropdown-menu dropdown-menu-end ${dropdownOpen ? 'show' : ''}`}>
                                    <li>
                                        <h6 className="dropdown-header">
                                            {user.email}
                                        </h6>
                                    </li>
                                    <li><hr className="dropdown-divider" /></li>
                                    <li>
                                        <button
                                            className="dropdown-item"
                                            onClick={handleLogout}
                                        >
                                            <i className="fas fa-sign-out-alt me-2"></i>
                                            Sign Out
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </nav>
    );
};

export default Header;