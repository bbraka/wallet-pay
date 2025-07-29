# Feature 004: OpenAPI Schema Integration

## Overview
Create OpenAPI schema annotations for Laravel models and provide a schema endpoint accessible from the React frontend. Include npm package integration for automatic frontend model generation from the OpenAPI schema.

## Objectives
1. Add OpenAPI annotations to existing Laravel models (User, Order, Transaction)
2. Create an API endpoint to serve the OpenAPI schema
3. Install and configure npm package for frontend model generation
4. Generate TypeScript interfaces/types from the OpenAPI schema
5. Ensure schema is accessible for React frontend consumption

## Models to Annotate
- User.php - wallet application user model
- Order.php - user order transactions
- Transaction.php - wallet credit/debit transactions

## Technical Requirements
- Use Laravel OpenAPI package for annotations
- Create `/api/schema` endpoint for schema access
- Install frontend package for schema-to-TypeScript conversion
- Configure build process to regenerate types when schema changes
- Ensure compatibility with existing React SPA structure

## Success Criteria
- All models have proper OpenAPI annotations
- Schema endpoint returns valid OpenAPI 3.0 specification
- Frontend can generate TypeScript types from schema
- Generated types match model structure
- Integration works with existing React components

## Dependencies
- Laravel OpenAPI package
- npm package for OpenAPI to TypeScript generation
- Proper CORS configuration for schema endpoint access