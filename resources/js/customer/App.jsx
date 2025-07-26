import React from 'react';
import { createRoot } from 'react-dom/client';

function CustomerApp() {
    return (
        <div className="container">
            <h1>User Wallet - Customer Portal</h1>
            <div className="row">
                <div className="col-md-8">
                    <div className="card">
                        <div className="card-header">
                            <h5>Welcome to Your Wallet</h5>
                        </div>
                        <div className="card-body">
                            <p>Customer dashboard content will be implemented here.</p>
                            <div className="mb-3">
                                <strong>Wallet Balance:</strong> $0.00
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

const container = document.getElementById('customer-app');
if (container) {
    const root = createRoot(container);
    root.render(<CustomerApp />);
}