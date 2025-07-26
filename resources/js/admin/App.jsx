import React from 'react';
import { createRoot } from 'react-dom/client';

function AdminApp() {
    return (
        <div className="container">
            <h1>User Wallet - Admin Portal</h1>
            <div className="row">
                <div className="col-md-12">
                    <div className="card">
                        <div className="card-header">
                            <h5>Admin Dashboard</h5>
                        </div>
                        <div className="card-body">
                            <p>Admin dashboard content will be implemented here.</p>
                            <div className="row">
                                <div className="col-md-4">
                                    <div className="card">
                                        <div className="card-body">
                                            <h6>Total Users</h6>
                                            <p>0</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="col-md-4">
                                    <div className="card">
                                        <div className="card-body">
                                            <h6>Total Orders</h6>
                                            <p>0</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="col-md-4">
                                    <div className="card">
                                        <div className="card-body">
                                            <h6>Total Transactions</h6>
                                            <p>0</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

const container = document.getElementById('admin-app');
if (container) {
    const root = createRoot(container);
    root.render(<AdminApp />);
}