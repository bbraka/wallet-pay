# User Wallet Application

A Laravel-based web application with React frontend for managing user wallets, orders, and transactions.

## Overview

This application provides a comprehensive wallet management system with:
- User wallet balance tracking
- Order processing with multiple status states
- Transaction management (credit/debit)
- Admin area with RBAC (Role-Based Access Control)
- Customer area with React SPA interface

## Architecture

- **Backend**: Laravel with Eloquent ORM
- **Frontend**: React SPA with Bootstrap 4 styling
- **Database**: MariaDB (latest stable)
- **Containerization**: Docker with Nginx
- **Testing**: Laravel Dusk for E2E testing

## API Documentation

### OpenAPI/Swagger Documentation

The application includes comprehensive API documentation using OpenAPI 3.0 specifications.

#### Accessing API Documentation

1. **OpenAPI Schema Endpoint**
   ```
   GET http://localhost:8000/api/schema
   ```
   Returns the complete OpenAPI 3.0 JSON specification for all API endpoints and models.

2. **Swagger UI Documentation**
   ```
   GET http://localhost:8000/api/documentation
   ```
   Interactive Swagger UI interface for exploring and testing API endpoints.

3. **Model Schemas**
   The following models are fully documented with OpenAPI annotations:
   - **User**: User accounts with wallet balances
   - **Order**: Order management with status tracking
   - **Transaction**: Wallet transactions (credit/debit)

#### For Frontend Developers

Generate TypeScript interfaces from the OpenAPI schema:

```bash
npm run generate-types
```

This creates TypeScript definitions in `resources/js/types/api.ts` that match your Laravel models exactly.

#### For Backend Developers

Update API documentation when models change:

```bash
php artisan l5-swagger:generate
```

## Quick Start

### Development Setup

1. **Start the application**
   ```bash
   docker compose up -d
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Run migrations**
   ```bash
   php artisan migrate --seed
   ```

4. **Generate API types**
   ```bash
   npm run generate-types
   ```

### Accessing the Application

- **Customer Area**: `http://localhost/customer`
- **Admin Area**: `http://localhost/admin`
- **API Documentation**: `http://localhost:8000/api/documentation`
- **API Schema**: `http://localhost:8000/api/schema`

## Development Workflow

### API Development

1. Update model OpenAPI annotations in `app/Models/`
2. Regenerate documentation: `php artisan l5-swagger:generate`
3. Update frontend types: `npm run generate-types`

### Frontend Development

1. Use generated TypeScript interfaces from `resources/js/types/api.ts`
2. All model properties and enums are type-safe
3. IDE autocompletion available for all API models

### Testing

Run comprehensive tests including backend and frontend:

```bash
# Backend tests
php artisan test

# Frontend E2E tests
php artisan dusk
```

## Project Structure

```
├── app/Models/              # Eloquent models with OpenAPI annotations
├── app/Http/Controllers/    # API controllers
├── resources/js/            # React frontend code
├── resources/js/types/      # Generated TypeScript interfaces
├── routes/api.php          # API routes
├── features/               # Feature documentation and reports
└── tests/Browser/          # Laravel Dusk tests
```

## Models

### User
- Wallet balance management
- Role-based access control
- Soft delete support

### Order
- Status: `pending_payment`, `completed`, `cancelled`, `refunded`
- Credit note generation for refunds
- User relationship

### Transaction
- Type: `credit`, `debit`
- Status: `active`, `cancelled`
- Order relationship (optional)
- Audit trail with created_by

## License

This project is proprietary software.
