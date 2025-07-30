# Admin CRUD Orders & Transactions Implementation Plan

## Overview
Implement comprehensive admin management interfaces for Orders and Transactions using Laravel Backpack, with advanced filtering, relationships, and full CRUD operations.

## Requirements Analysis

### Orders CRUD Requirements
- Table view with all orders (default sort: ID DESC)
- Display columns: Order ID, Date, Status, Type, Provider, User Name/Email, Receiver Name/Email
- Advanced filters: Date range, Select2 for emails, Dropdowns for status/type/provider
- CRUD operations: View, Update, Delete existing orders
- Create functionality: Admin can create top-up orders for any user
- Clickable user columns linking to user admin profiles

### Transactions CRUD Requirements
- Table view with all transactions (default sort: ID DESC)
- Display columns: Type, Status, Description excerpt, Created By (linked), Order ID (linked), Created At
- Filters: Dropdowns for type/status, Date range, Select2 for created_by, Text input for order_id
- CRUD operations: Full Create/Read/Update/Delete
- Auto-fill created_by from admin session
- Automatic +/- for credit/debit amounts

## Technical Architecture

### 1. Backpack CRUD Controllers
**Orders Management**
- `app/Http/Controllers/Admin/OrderCrudController.php`
- Thin controller using existing OrdersService
- Custom queries for user joins
- Link generation for user profiles

**Transactions Management**
- `app/Http/Controllers/Admin/TransactionCrudController.php`
- Leverage existing transaction services
- Auto-populate admin user data

### 2. Service Layer Extensions
**OrdersService Enhancements**
- `createAdminTopUp(User $targetUser, array $data, User $admin)`
- `getOrdersWithRelationsForAdmin(array $filters)`
- Admin-specific validation rules

**TransactionService Creation**
- `app/Services/Admin/TransactionService.php`
- `createManualTransaction(array $data, User $admin)`
  - Validates user has sufficient balance for debit transactions
  - Throws ValidationException if insufficient funds
- `updateTransaction(Transaction $transaction, array $data)`
- Balance validation and updates

### 3. Database Queries Optimization
- Eager load relationships: `with(['user', 'receiver', 'topUpProvider'])`
- Left joins for user data in listing queries
- Index optimization for filtering columns

### 4. Backpack Configuration

#### Orders CRUD Setup
```php
// Columns Configuration
- 'id' => ['label' => 'Order ID', 'type' => 'number']
- 'created_at' => ['label' => 'Date', 'type' => 'datetime']
- 'status' => ['label' => 'Status', 'type' => 'select_from_array']
- 'order_type' => ['label' => 'Type', 'type' => 'select_from_array']
- 'topUpProvider.name' => ['label' => 'Provider', 'type' => 'text']
- 'user' => ['label' => 'User', 'type' => 'custom_html', 'searchLogic' => custom]
- 'receiver' => ['label' => 'Receiver', 'type' => 'custom_html', 'searchLogic' => custom]

// Filters Configuration
- DateRangeFilter for created_at
- Select2Filter for user.email, receiver.email
- SelectFilter for status, order_type, provider
```

#### Transactions CRUD Setup
```php
// Columns Configuration
- 'type' => ['label' => 'Type', 'type' => 'enum']
- 'status' => ['label' => 'Status', 'type' => 'enum']
- 'description' => ['label' => 'Description', 'type' => 'text', 'limit' => 50]
- 'creator' => ['label' => 'Created By', 'type' => 'relationship']
- 'order_id' => ['label' => 'Order', 'type' => 'custom_html']
- 'created_at' => ['label' => 'Date', 'type' => 'datetime']

// Filters Configuration
- SelectFilter for type, status
- DateRangeFilter for created_at
- Select2Filter for created_by
- TextFilter for order_id
```

### 5. Custom Views & Components

#### Orders Views
- `resources/views/admin/orders/columns/user_link.blade.php`
- `resources/views/admin/orders/columns/receiver_link.blade.php`
- `resources/views/admin/orders/fields/admin_topup_form.blade.php`

#### Transactions Views
- `resources/views/admin/transactions/columns/order_link.blade.php`
- `resources/views/admin/transactions/columns/creator_link.blade.php`
- `resources/views/admin/transactions/fields/amount_input.blade.php`
- `resources/views/admin/transactions/fields/balance_check.js` - Client-side validation

#### Admin Menu Configuration
- Add Orders menu item in `app/Providers/AppServiceProvider.php` or Backpack config
- Add Transactions menu item below Orders
- Set appropriate icons (e.g., shopping-cart for Orders, exchange for Transactions)

### 6. Events & Listeners

**New Events**
- `AdminOrderCreated` - When admin creates order
- `AdminTransactionCreated` - When admin creates transaction
- `TransactionManuallyUpdated` - When admin updates transaction

**Enhanced Listeners**
- Log admin actions for audit trail
- Send notifications to affected users
- Update user balances accordingly

### 7. Validation & Business Rules

#### Orders Admin Rules (Client & Server Side)
- Admin can create only top-up orders (not transfers)
- Amount limits apply (Order::MAX_TOP_UP_AMOUNT)
  - Client: HTML5 max attribute + JavaScript validation
  - Server: Laravel validation rules
- Target user must exist and be active
  - Client: Select2 with only active users
  - Server: exists:users,id + active check
- Provider must be active
  - Client: Dropdown shows only active providers
  - Server: exists:top_up_providers,id where is_active=1

#### Transactions Admin Rules (Client & Server Side)
- Amount must match type (positive for credit, negative for debit)
  - Client: JavaScript auto-formatting and validation
  - Server: Custom validation rule based on type
- Cannot modify system-generated transactions
  - Client: Disable edit buttons for system transactions
  - Server: Policy check before update
- **Debit transactions cannot exceed user's current wallet balance**
  - Client: AJAX balance check on amount input
  - Server: Real-time balance validation in TransactionService
- Balance checks enforced before creating debit transactions
- Audit trail for all manual transactions

### 8. Testing Strategy

#### Unit Tests
**Orders Admin Tests**
- `tests/Unit/Services/Admin/OrdersServiceTest.php`
  - Test admin order creation
  - Test filtering with relationships
  - Test validation rules

**Transactions Service Tests**
- `tests/Unit/Services/Admin/TransactionServiceTest.php`
  - Test manual transaction creation
  - Test balance updates
  - Test validation logic

#### Feature Tests
**Orders CRUD Tests**
- `tests/Feature/Admin/OrdersCrudTest.php`
  - Test all CRUD operations
  - Test filtering functionality
  - Test authorization

**Transactions CRUD Tests**
- `tests/Feature/Admin/TransactionsCrudTest.php`
  - Test CRUD operations
  - Test auto-population
  - Test amount handling

#### Laravel Dusk Tests
**Orders UI Tests**
- `tests/Browser/Admin/OrdersManagementTest.php`
  - Test table display and sorting
  - Test filter interactions
  - Test create top-up flow
  - Test user profile links

**Transactions UI Tests**
- `tests/Browser/Admin/TransactionsManagementTest.php`
  - Test table functionality
  - Test create transaction flow
  - Test amount auto-formatting
  - Test order links

### 9. Security Considerations
- Admin permission checks on all operations
- Audit logging for all admin actions
- CSRF protection on forms
- XSS prevention in custom HTML columns
- SQL injection prevention in custom queries

### 10. Performance Optimizations
- Pagination for large datasets (25 items per page)
- Indexed columns for filtering
- Eager loading to prevent N+1 queries
- Lazy loading for Select2 filters

## Implementation Steps

### Phase 1: Orders CRUD
1. Create OrderCrudController with Backpack setup
2. Implement custom columns for user/receiver links
3. Add filters and search logic
4. Create admin top-up form
5. Extend OrdersService for admin operations
6. Add "Orders" menu item to admin sidebar
7. Write unit and feature tests

### Phase 2: Transactions CRUD
1. Create TransactionService with wallet balance validation
2. Create TransactionCrudController
3. Implement custom columns and filters
4. Create transaction form with amount handling
5. Add validation and business logic (including balance checks)
6. Add "Transactions" menu item to admin sidebar
7. Write comprehensive tests

### Phase 3: Integration & Testing
1. Create Laravel Dusk test environment
2. Write browser automation tests
3. Test complete workflows
4. Performance testing and optimization
5. Security audit

### Phase 4: Polish & Documentation
1. Add tooltips and help text
2. Implement bulk operations if needed
3. Create admin user documentation
4. Add activity logging dashboard
5. **Configure admin menu items for Orders and Transactions**

## Dependencies

### Required Packages
- **Already Installed**: 
  - `backpack/crud` - Core CRUD functionality
  - `backpack/permissionmanager` - Permission management

### Additional Packages Needed
- **Select2 Ajax Filter**: Built into Backpack Pro (need to verify license)
- **Date Range Filter**: Built into Backpack Pro
- Alternative: `backpack/filters` community package

### Development Dependencies
- Laravel Dusk for browser testing
- Additional Chrome/Chromium setup for Dusk

## Success Criteria
- All CRUD operations functional for both Orders and Transactions
- Advanced filtering works smoothly with good UX
- User/Order links navigate correctly
- Admin actions are logged and auditable
- All tests pass with >90% coverage
- Performance: Page load <2s with 10k records
- Security: No vulnerabilities in OWASP top 10

## Estimated Timeline
- Phase 1 (Orders CRUD): 2-3 days
- Phase 2 (Transactions CRUD): 2-3 days
- Phase 3 (Testing): 2 days
- Phase 4 (Polish): 1 day
- **Total**: 7-9 days

## Risk Mitigation
- **Backpack Version Compatibility**: Test with current version first
- **Performance Issues**: Implement pagination early, add indexes
- **Complex Queries**: Use Eloquent where possible, raw SQL as last resort
- **Testing Environment**: Ensure Dusk works in Docker environment