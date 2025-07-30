# Withdrawal Orders Feature Implementation Plan

## Updated Requirements Analysis
**Feature Requirements:**
- User withdrawal requests (merchants can only withdraw from own account)
- Admin withdrawal/refunds (admins can withdraw from any account)
- Prevent withdrawals exceeding user balance
- New admin submenu for pending payments/withdrawals with bulk approval
- Payment completion date tracking
- Event-driven architecture (Controllers → Events → Listeners → Services)

## Current System Analysis
**✅ Existing:**
- **Order Types**: `internal_transfer`, `user_top_up`, `admin_top_up` 
- **Order Statuses**: `pending_payment`, `completed`, `cancelled`, `refunded`
- **Architecture**: Event-driven with OrderService, Merchant/OrdersService

**❌ Missing:**
- Withdrawal order types
- `pending_approval` status for withdrawal requests
- `payment_completion_date` column
- Admin bulk approval interface

## Implementation Plan

### 1. Database Migration
**Create migration:** `add_withdrawal_support_to_orders_table`
- **Update order_type enum**: Add `'user_withdrawal'`, `'admin_withdrawal'`
- **Update status enum**: Add `'pending_approval'`
- **Add column**: `payment_completion_date TIMESTAMP NULL`
- **Update existing orders**: Set `payment_completion_date` for completed orders

### 2. Enum Updates (No Business Logic)
- **OrderType.php**: Add `USER_WITHDRAWAL`, `ADMIN_WITHDRAWAL` cases
- **OrderStatus.php**: Add `PENDING_APPROVAL` case
- **Order.php**: Add `payment_completion_date` to fillable, add casts

### 3. Event-Driven Architecture
**New Events:**
- `WithdrawalRequested` - Triggered when user/admin creates withdrawal
- `WithdrawalApproved` - Triggered when admin approves withdrawal
- `WithdrawalDenied` - Triggered when admin denies withdrawal
- `OrderCompleted` - Enhanced to set payment_completion_date

**New Event Listeners:**
- `ProcessWithdrawalRequest` - Validates balance, creates order
- `ProcessWithdrawalApproval` - Processes approved withdrawal via WalletService
- `ProcessWithdrawalDenial` - Cancels withdrawal order
- `SetPaymentCompletionDate` - Sets completion date when order status changes to completed

### 4. Service Layer (Business Logic Only)
**OrderService.php additions:**
- `processWithdrawalRequest(User $user, float $amount, string $description)`
- `approveWithdrawal(Order $order)`
- `denyWithdrawal(Order $order)`
- Balance validation methods

**Merchant/OrdersService.php additions:**
- `createWithdrawalRequest(User $user, array $data)`

### 5. Controllers (Event Triggers Only)
**Merchant/OrdersController.php:**
- `withdrawal()` method - dispatches `WithdrawalRequested` event

**Admin/OrderCrudController.php:**
- `approveWithdrawal()` - dispatches `WithdrawalApproved` event
- `denyWithdrawal()` - dispatches `WithdrawalDenied` event
- `bulkApprove()` - dispatches multiple `WithdrawalApproved` events

### 6. Admin Interface Enhancements
**New Admin Submenu:** "Pending Approvals"
- **Route**: `/admin/orders/pending-approvals`
- **Features**:
  - Table with checkbox column for bulk selection
  - Bulk approval button with confirmation modal
  - Individual deny buttons with confirmation alerts
  - Ordered by `created_at ASC` (oldest first)
  - Filter: `status IN ('pending_payment', 'pending_approval')`

**Table Columns:**
- Checkbox, Order ID, Type, User, Amount, Description, Created Date, Actions (Deny)

### 7. API Endpoints
- `POST /api/merchant/orders/withdrawal` - Create withdrawal request
- `PUT /api/admin/orders/{id}/approve` - Approve withdrawal/payment
- `PUT /api/admin/orders/{id}/deny` - Deny withdrawal/payment
- `POST /api/admin/orders/bulk-approve` - Bulk approve selected orders

### 8. Business Rules & Validation
- **Balance Check**: Users cannot withdraw more than their current balance
- **Permissions**: Merchants can only withdraw from own account
- **Admin Powers**: Admins can withdraw from any account (refund scenario)
- **Status Flow**: `pending_approval` → `completed` or `cancelled`
- **Completion Tracking**: Auto-set `payment_completion_date` on completion

### 9. Testing Strategy
- **Unit Tests**: Service methods, event listeners, validation logic
- **Feature Tests**: API endpoints, event dispatching
- **Browser Tests (Dusk)**: 
  - Merchant withdrawal creation flow
  - Admin bulk approval interface
  - Individual denial workflow
  - Confirmation modals and alerts

### 10. Implementation Order
1. Create database migration for enums and payment_completion_date
2. Update OrderType and OrderStatus enums
3. Create new events and event listeners
4. Extend OrderService with withdrawal business logic
5. Update controllers to dispatch events (no business logic)
6. Create admin pending approvals interface
7. Implement API endpoints
8. Write comprehensive tests
9. Update API documentation

### 11. Key Architecture Principles
- **Controllers**: Only dispatch events, no business logic
- **Events**: Carry data between layers
- **Listeners**: Call appropriate services
- **Services**: Contain all business logic
- **Models**: Data structure only, no business methods