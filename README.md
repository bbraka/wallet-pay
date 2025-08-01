# User Wallet Application

Laravel + React wallet management system with Docker containerization.

**Core Features**: User wallets, order processing, transactions, admin RBAC, React SPA frontend

**Tech Stack**: Laravel, React, MariaDB, Docker, OpenAPI/Swagger

## Quick Start

```bash
# Setup environment
cp .env.example .env
# Edit .env with your database passwords and GitHub token

# Start containers (auto-installs dependencies & runs migrations)
docker compose up --build -d

# Access application
# - Customer Area: http://localhost:8000/  
# - Admin Area: http://localhost:8000/admin
# - API Docs: http://localhost:8000/api/documentation
```

## Container Management

### Full Rebuild
```bash
docker compose down -v
docker compose up --build -d
```

### Troubleshooting

**Database Issues**: `docker compose logs mysql` then `docker compose restart mysql`

**Migration/Test Issues**: Use full rebuild (recreates all databases and runs migrations):
```bash
docker compose down -v
docker compose up --build -d
```

## Development

**API Types**: `npm run generate-types` (creates TypeScript interfaces from OpenAPI schema)

**Testing**: 
- Backend: `docker compose exec app php artisan test`
- E2E: `docker compose exec app php artisan dusk`

**Ports**: App (8000), Database (3307)

## Core Models

- **User**: Wallet balances, RBAC
- **Order**: Status tracking (`pending_payment`, `completed`, `cancelled`, `refunded`)  
- **Transaction**: Credit/debit entries with audit trail
