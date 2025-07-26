# Scaffolding Implementation Plan for User Wallet Application

## Overview
This plan outlines the complete scaffolding setup for a Laravel + React application with user wallet functionality, including comprehensive testing infrastructure.

## Pre-Implementation Steps
1. Check currently running MCP servers
2. Create feature/reports folder structure for status reports

## Phase 1: Package Research & Installation
**Priority: High**

### 1.1 RBAC Package
- **Package**: `spatie/laravel-permission`
- **Reason**: Most popular Laravel RBAC package, actively maintained with 12k+ stars
- **Alternative**: If not suitable, document for separate feature implementation

### 1.2 Core Laravel Packages
```bash
composer require darkaonline/l5-swagger    # OpenAPI annotations
composer require barryvdh/laravel-dompdf   # PDF generation
composer require laravel/sanctum           # API authentication
```

### 1.3 Database Migrations
- Use Laravel's built-in migration system (no additional package needed)

## Phase 2: Database Setup
**Priority: High**

### 2.1 Create Migrations
1. **users table**
   - Standard Laravel fields
   - Add `wallet_amount` DECIMAL(10,2) DEFAULT 0.00
   - Soft deletes

2. **orders table**
   - id, title, amount, description
   - status ENUM: pending_payment, completed, cancelled, refunded
   - credit_note_number (unique, nullable)
   - user_id foreign key
   - Soft deletes

3. **transactions table**
   - Links users and orders
   - type ENUM: credit, debit
   - status ENUM: active, cancelled
   - created_by foreign key

### 2.2 RBAC Tables
- Run `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- Run migrations to create roles, permissions, and pivot tables

### 2.3 Rollback Migrations
- Create down() methods for all migrations

## Phase 3: Project Structure Creation
**Priority: Medium**

### 3.1 Directory Structure
```
app/
├── Console/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   └── Customer/
│   └── Middleware/
├── Jobs/
├── Listeners/
├── Models/
├── Services/
│   ├── Admin/
│   └── Customer/
```

### 3.2 Frontend Structure
```
resources/
├── js/
│   ├── admin/
│   │   ├── App.jsx
│   │   ├── components/
│   │   └── __tests__/
│   └── customer/
│       ├── App.jsx
│       ├── components/
│       └── __tests__/
├── sass/
│   └── app.scss
└── views/
    ├── admin.blade.php
    └── customer.blade.php
```

### 3.3 Test Structure
```
tests/
├── Feature/
│   ├── Admin/
│   └── Customer/
├── Unit/
├── Integration/
└── e2e/
    ├── admin/
    └── customer/
```

## Phase 4: Frontend Setup
**Priority: Medium**

### 4.1 Install Dependencies
```bash
npm install react react-dom
npm install bootstrap@4
npm install sass
npm install --save-dev @vitejs/plugin-react
```

### 4.2 Testing Dependencies
```bash
npm install --save-dev jest @testing-library/react @testing-library/jest-dom
npm install --save-dev @testing-library/user-event
npm install --save-dev puppeteer jest-puppeteer
```

### 4.3 Configure Vite
- Set up vite.config.js for React compilation
- Configure separate entry points for admin and customer areas
- Enable SASS preprocessing

## Phase 5: Server Configuration
**Priority: Medium**

### 5.1 Nginx Configuration
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass php:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 5.2 Laravel Configuration
- Enable pretty URLs in RouteServiceProvider
- Configure API routes with proper middleware

## Phase 6: Testing Infrastructure
**Priority: Medium**

### 6.1 Backend Testing (PHPUnit)
- Configure test database in phpunit.xml
- Set up `.env.testing` file
- Create base test cases for Feature and Unit tests

### 6.2 Frontend Unit Testing (Jest + React Testing Library)
- Configure jest.config.js
- Set up test utilities and custom renders
- Create example tests for components

### 6.3 E2E Testing (Puppeteer)
- Configure jest-puppeteer.config.js
- Set up headless and headed modes
- Create page object models for common interactions

### 6.4 Test Reporting
- Configure coverage reports for all test types
- Set up CI/CD integration points

## Phase 7: Initial Implementation
**Priority: Low**

### 7.1 Environment Configuration
- Create `.env.example` with all required variables
- Create `.env` for development
- Create `.env.production` template

### 7.2 Models
```php
// User Model
- Implement wallet_amount accessor/mutator
- Set up RBAC traits
- Add relationships

// Order Model
- Define status constants
- Add user relationship
- Implement credit note generation

// Transaction Model  
- Define type/status constants
- Add relationships to user and order
```

### 7.3 Simple Home Page
- Create React component showing "User Wallet App"
- Add Bootstrap styling
- Include navigation placeholder

## Phase 8: Documentation
**Priority: Low**

### 8.1 Create README sections for:
- Installation instructions
- Development setup
- Testing procedures
- Deployment guide

### 8.2 API Documentation
- Configure Swagger/OpenAPI
- Document authentication endpoints
- Create example annotations

## Deliverables Checklist
- [ ] All packages installed and configured
- [ ] Database structure created with migrations
- [ ] All directories created (even if empty)
- [ ] RBAC system integrated
- [ ] React + Bootstrap configured
- [ ] Testing infrastructure ready
- [ ] Simple home page functional
- [ ] Environment files configured
- [ ] Nginx serving Laravel correctly
- [ ] Empty but structured codebase ready for features
- [ ] Backend tests passing
- [ ] Frontend tests passing
- [ ] Status report written in features/reports/001-scaffolding-report.md

## Post-Implementation Steps
1. Run all backend tests (PHPUnit)
2. Run all frontend tests (Jest + Puppeteer)
3. Write comprehensive status report documenting:
   - Packages installed
   - Any issues encountered
   - RBAC package decision
   - Test results
   - Next steps

## Notes
- No test data will be seeded - tables remain empty
- All packages must be actively maintained (commits within last year)
- If suitable RBAC package not found, document in report for separate feature
- Follow Laravel and React best practices throughout
- Must test both backend and frontend after implementation
- Status report is mandatory after feature completion