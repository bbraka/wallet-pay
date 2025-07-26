# Project Architecture

## Overview
The web application is inside a docker container. It is a php app which uses the laravel framework for backend. It uses Eloquent. It will use react for frontend with bootstrap 4 for styling. The database is mariadb latest stable version. All php modules are added to the docker configurations. REST endpoints will use openapi annotations. Models in the backend will also be annotated to create a schema for them. Styling will be preprocessed with sass. The container will use nginx to serve the application. Url routing will be pretty so we need proper nginx and laravel configuration. Unit, functional, and integration tests will be performed for each api endpoints and services. All packages are added through composer. Controllers must be thin. Business logic is in services. Controllers send events, listeners call services to execute actions regarding the events. Database structure is added via migrations. All packages which are added must be compatible with latest version of laravel, and must be actively maintained- new commits within the last year and thousands of downloads. Pdfs and reports are generated using jobs. The frontend is a spa. All controllers except the main entry points for the user and admin areas are rest controllers. The customer area and admin area controllers are located in separate folders. The environment variables will be saved in .env files. There will be an option to set a development and production environments. The application will default to a development environment.

Users are two types - customers and administrators. User roles are managed in the admin area by a RBAC page. These roles determine which api endpoints are accessible to each user.

# MCP Usage
Before implementing a feature - always check the currently running MCP servers

# Feature Creation

After successfully craeting a feature - test backend and frontend.
Write a Status Report in the feature/reports folder

## General Project Structure

```
my-app/
├── app/
│   ├── Console/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   └── Customer/
│   │   └── Middleware/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Services/
│   │   ├── Admin/
│   │   └── Customer/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       └── Dockerfile
├── public/
│   └── build/
├── resources/
│   ├── js/
│   │   ├── admin/
│   │   │   ├── App.jsx
│   │   │   └── components/
│   │   └── customer/
│   │       ├── App.jsx
│   │       └── components/
│   ├── sass/
│   │   └── app.scss
│   └── views/
│       ├── admin.blade.php
│       └── customer.blade.php
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── tests/
│   ├── Feature/
│   │   ├── Admin/
│   │   └── Customer/
│   ├── Unit/
│   └── Integration/
├── .env
├── .env.production
├── composer.json
├── package.json
├── phpunit.xml
├── vite.config.js
└── artisan
```

## Database Tables

### users
| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT |
| name | VARCHAR(255) | NOT NULL |
| email | VARCHAR(255) | UNIQUE, NOT NULL |
| wallet_amount | DECIMAL(10,2) | NOT NULL, DEFAULT 0.00 |
| password | VARCHAR(255) | NOT NULL |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NULL |
| deleted_at | TIMESTAMP | NULL |

### orders
| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT |
| title | VARCHAR(255) | NOT NULL |
| amount | DECIMAL(10,2) | NOT NULL |
| status | ENUM('pending_payment', 'completed', 'cancelled', 'refunded') | NOT NULL |
| description | TEXT | NULL |
| user_id | BIGINT UNSIGNED | FOREIGN KEY (users.id) |
| credit_note_number | VARCHAR(255) | UNIQUE, NULL |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NULL |
| deleted_at | TIMESTAMP | NULL |

### transactions
| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FOREIGN KEY (users.id) |
| type | ENUM('credit', 'debit') | NOT NULL |
| amount | DECIMAL(10,2) | NOT NULL |
| status | ENUM('active', 'cancelled') | NOT NULL |
| description | TEXT | NULL |
| created_by | BIGINT UNSIGNED | FOREIGN KEY (users.id) |
| order_id | BIGINT UNSIGNED | FOREIGN KEY (orders.id), NULL |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NULL |

### RBAC
These tables are created with a migration from a RBAC Php package (if one is available, or will be added in a feature)