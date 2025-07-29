# Feature 002: Admin Area with Backpack & RBAC

## Overview
Replace React admin area with Laravel Backpack and implement role-based access control (RBAC) using Spatie Laravel Permission package.

## Implementation Steps

### 1. Install and Configure Backpack
- Install Laravel Backpack CRUD package
- Publish and configure Backpack assets and config files
- Set up basic Backpack authentication and routing

### 2. Install RBAC System
- Install Laravel Backpack PermissionManager package (`backpack/permissionmanager`)
  - This automatically installs and configures Spatie Laravel Permission package
  - Provides admin interface for managing users, roles, and permissions
- Run migrations for roles and permissions tables
- Configure User model to use HasRoles trait and CrudTrait

### 3. Create Roles and Test User
- Create seeder for 'admin' and 'customer' roles
- Create admin test user with admin role
- Set up middleware to restrict admin area access to admin role only

### 4. Configure Backpack Admin Interface
- PermissionManager automatically provides CRUD interfaces for:
  - Users management
  - Roles management  
  - Permissions management
- Add RBAC menu items to Backpack sidebar using provided menu configuration
- Configure User model binding if using custom User model

### 5. Remove React Admin Logic
- Remove React components for admin area
- Update routing to use Backpack instead of React for admin
- Clean up unused admin React assets

### 6. Testing
- Create feature tests for RBAC functionality
- Test admin area access with different user roles
- Test RBAC management interface

## Package Information
**Laravel Backpack PermissionManager** (`backpack/permissionmanager`)
- **GitHub**: Laravel-Backpack/PermissionManager
- **Features**: 
  - Admin interface for Spatie Laravel Permission
  - User, Role, and Permission CRUD operations
  - Multiple roles per user support
  - Direct permissions in addition to role permissions
  - Blade directives for permission checking
  - Integration with Backpack's access control system

## Expected Outcomes
- Admin area accessible only to users with 'admin' role
- Backpack-based interface for managing users, roles, and permissions
- Clean separation between customer (React) and admin (Backpack) areas
- Full RBAC system with granular permission control