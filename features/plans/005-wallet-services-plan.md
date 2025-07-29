# Wallet Services Implementation Plan

## Overview
This feature implements a comprehensive wallet system for users to manage money transfers between each other and handle external top-ups. The system will support internal transfers between users, user-initiated top-ups, and admin-initiated top-ups with proper provider tracking for audit purposes.

## Requirements Analysis

### Database Changes Required
1. **Remove** `credit_note_number` column from orders table
2. **Add** `order_type` enum column to orders table with values: `internal_transfer`, `user_top_up`, `admin_top_up`
3. **Add** `receiver_user_id` column to orders table for internal transfers
4. **Create** `top_up_providers` table for tracking funding sources
5. **Add** `top_up_provider_id` column to orders table (for top-up orders)
6. **Add** `provider_reference` column to orders table (external reference number)

### New Database Tables

#### top_up_providers
| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT |
| name | VARCHAR(255) | NOT NULL, UNIQUE |
| code | VARCHAR(50) | NOT NULL, UNIQUE |
| description | TEXT | NULL |
| is_active | BOOLEAN | NOT NULL, DEFAULT true |
| requires_reference | BOOLEAN | NOT NULL, DEFAULT false |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NULL |

**Default Providers**: BANK, CASH, MONEY_ORDER, CREDIT_CARD, PAYPAL, ADMIN_ADJUSTMENT

### Core Services to Implement
1. **WalletTransactionService** - Handles all wallet balance operations
2. **OrderService** - Manages order creation, confirmation, cancellation, and top-up provider operations
3. **Event System** - Handles wallet operation events and listeners

### Enum Management Strategy
**Approach**: Use native PHP 8.1+ Enums with Laravel casting (recommended for Laravel 9+)

**Benefits**:
- Type safety and IDE autocompletion
- Centralized enum definitions in models
- Database validation consistency
- Easy testing and refactoring

## Implementation Plan

### Phase 1: Database Structure Updates
1. **Update CLAUDE.md**
   - Remove `credit_note_number` from orders table documentation
   - Add `order_type`, `receiver_user_id`, `top_up_provider_id`, and `provider_reference` columns
   - Add `top_up_providers` table documentation

2. **Create Migrations**
   - Create `top_up_providers` table with default providers seeded
   - Remove `credit_note_number` column from orders table
   - Keep `type` enum column in transactions table (`credit`, `debit`)
   - Add `order_type` enum: `internal_transfer`, `user_top_up`, `admin_top_up`
   - Add `receiver_user_id` foreign key (nullable for top_up orders)
   - Add `top_up_provider_id` foreign key (nullable for internal transfers)
   - Add `provider_reference` varchar field (for external references)
   - Update `amount` column to allow negative values (negative = withdrawal, positive = deposit)

### Phase 2: Enum Definitions

#### 2.1 Create Enum Classes (`app/Enums/`)

**OrderType.php**:
```php
<?php

namespace App\Enums;

enum OrderType: string
{
    case INTERNAL_TRANSFER = 'internal_transfer';
    case USER_TOP_UP = 'user_top_up';
    case ADMIN_TOP_UP = 'admin_top_up';

    public function label(): string
    {
        return match($this) {
            self::INTERNAL_TRANSFER => 'Internal Transfer',
            self::USER_TOP_UP => 'User Top-up',
            self::ADMIN_TOP_UP => 'Admin Top-up',
        };
    }
}
```

**OrderStatus.php**:
```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }
}
```

**TransactionType.php**:
```php
<?php

namespace App\Enums;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match($this) {
            self::CREDIT => 'Credit',
            self::DEBIT => 'Debit',
        };
    }
}
```

**TransactionStatus.php**:
```php
<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::CANCELLED => 'Cancelled',
        };
    }
}
```

#### 2.2 Model Integration

**Order Model**:
```php
use App\Enums\{OrderType, OrderStatus};

class Order extends Model
{
    protected $casts = [
        'order_type' => OrderType::class,
        'status' => OrderStatus::class,
    ];
}
```

**Transaction Model**:
```php
use App\Enums\{TransactionType, TransactionStatus};

class Transaction extends Model
{
    protected $casts = [
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
    ];
}
```

### Phase 3: Core Services Implementation

#### 3.1 WalletTransactionService (`app/Services/WalletTransactionService.php`)
**Purpose**: Handle all wallet balance operations with proper validation and database transactions

**Methods**:
- `add(User $user, float $amount, string $description, ?Order $order = null): Transaction`
  - Creates credit transaction with positive amount using `TransactionType::CREDIT`
- `withdraw(User $user, float $amount, string $description, ?Order $order = null): Transaction`
  - Creates debit transaction with negative amount using `TransactionType::DEBIT`
- `cancel(Transaction $transaction): void`
  - Sets status to `TransactionStatus::CANCELLED`
- `calculateUserBalance(User $user): float`
  - Sums all transactions with `TransactionStatus::ACTIVE` only

**Usage Examples**:
```php
// Add money
$transaction = $walletService->add($user, 100.00, 'Top-up from bank', $order);
// Creates: type=TransactionType::CREDIT, amount=100.00, status=TransactionStatus::ACTIVE

// Withdraw money  
$transaction = $walletService->withdraw($user, 50.00, 'Transfer to John', $order);
// Creates: type=TransactionType::DEBIT, amount=-50.00, status=TransactionStatus::ACTIVE
```

**Features**:
- Database transaction wrapping for atomicity
- Balance validation before withdrawals
- Exception handling with detailed error messages
- Automatic wallet_amount recalculation
- Type-safe enum usage throughout

#### 3.2 OrderService (`app/Services/OrderService.php`)
**Purpose**: Manage order lifecycle, business logic, and top-up provider operations

**Order Management Methods**:
- `createInternalTransferOrder(User $sender, User $receiver, float $amount, string $title, ?string $description = null): Order`
- `createUserTopUpOrder(User $user, float $amount, string $title, TopUpProvider $provider, ?string $providerReference = null, ?string $description = null): Order`
- `createAdminTopUpOrder(User $targetUser, User $admin, float $amount, string $title, TopUpProvider $provider, ?string $providerReference = null, ?string $description = null): Order`
- `confirmPayment(Order $order, User $confirmer): void`
- `rejectPayment(Order $order, User $rejector): void`
- `refundOrder(Order $order, User $refunder): void`

**Provider Management Methods**:
- `getAllActiveProviders(): Collection`
- `getProviderByCode(string $code): TopUpProvider`
- `validateProviderReference(TopUpProvider $provider, ?string $reference): bool`
- `createProvider(string $name, string $code, ?string $description = null, bool $requiresReference = false): TopUpProvider`
- `deactivateProvider(TopUpProvider $provider): void`

**Features**:
- Balance checks before creating transfer orders
- Automatic status management based on order type
- Provider validation and reference handling
- Event dispatching for wallet operations
- Complete audit trail with provider tracking
- Provider lifecycle management within order context

### Phase 3: Event System

#### 3.1 Events (`app/Events/`)
- `MoneyWithdrawnEvent(User $user, float $amount, Order $order)`
- `MoneyAddedEvent(User $user, float $amount, Order $order)`
- `TransactionCancelledEvent(Transaction $transaction)`

#### 3.2 Listeners (`app/Listeners/`)
- `ProcessMoneyWithdrawalListener` - Creates debit transaction
- `ProcessMoneyAdditionListener` - Creates credit transaction  
- `ProcessTransactionCancellationListener` - Updates transaction status

#### 3.3 Model Observers
- `TransactionObserver` - Automatically recalculates user wallet_amount on transaction updates

### Phase 4: Order Flow Implementation

#### 4.1 Internal Transfer Flow
1. **Order Creation**:
   - Validate sender has sufficient balance
   - Create order with `pending_payment` status
   - Dispatch `MoneyWithdrawnEvent`
   - Listener creates debit transaction

2. **Payment Confirmation**:
   - Receiver confirms payment
   - Order status → `completed`
   - Dispatch `MoneyAddedEvent`
   - Listener creates credit transaction for receiver

3. **Payment Rejection**:
   - Receiver or sender rejects payment
   - Order status → `cancelled`
   - Dispatch `TransactionCancelledEvent`
   - Listener updates transaction status to `cancelled`

#### 4.2 User Top-Up Flow
1. **Order Creation**:
   - User initiates top-up with provider selection
   - Create order with `pending_payment` status
   - Wait for external payment confirmation
   - Manual or automated confirmation triggers completion

2. **Order Completion**:
   - Order status → `completed`
   - Dispatch `MoneyAddedEvent`
   - Listener creates credit transaction

#### 4.3 Admin Top-Up Flow
1. **Order Creation**:
   - Admin creates top-up for any user
   - Select provider (ADMIN_ADJUSTMENT, CASH, etc.)
   - Optionally add provider reference
   - Create order with `completed` status (immediate)
   - Dispatch `MoneyAddedEvent`
   - Listener creates credit transaction

**Key Differences**:
- Admin top-ups complete immediately (no external validation needed)
- Full audit trail with admin user tracking
- Provider reference for reconciliation

### Phase 5: Database Triggers and Observers

#### 5.1 TransactionObserver
**Purpose**: Automatically recalculate user wallet_amount when transactions change

**Events Handled**:
- `created` - Recalculate balance after new transaction
- `updated` - Recalculate balance after status changes
- `deleted` - Recalculate balance after transaction removal

**Logic**:
```php
use App\Enums\TransactionStatus;

$balance = Transaction::where('user_id', $transaction->user_id)
    ->where('status', TransactionStatus::ACTIVE) // Only include active transactions
    ->sum('amount'); // Direct sum since amounts are already positive/negative

User::where('id', $transaction->user_id)->update(['wallet_amount' => $balance]);
```

### Phase 6: Testing Strategy

#### 6.1 Unit Tests (`tests/Unit/`)
- `WalletTransactionServiceTest.php`
  - Test add/withdraw/cancel operations
  - Test balance calculations
  - Test error handling and validations
  
- `OrderServiceTest.php`
  - Test order creation flows (internal transfers, user top-ups, admin top-ups)
  - Test confirmation/rejection logic
  - Test refund operations
  - Test provider management methods
  - Test provider validation and reference handling

#### 6.2 Feature Tests (`tests/Feature/`)
- `WalletOperationsTest.php`
  - End-to-end transfer scenarios
  - Top-up scenarios
  - Error conditions and edge cases

#### 6.3 Integration Tests
- Complete user workflows
- Multi-user transfer scenarios
- Concurrent operation handling

## File Structure

```
app/
├── Enums/
│   ├── OrderType.php
│   ├── OrderStatus.php
│   ├── TransactionType.php
│   └── TransactionStatus.php
├── Events/
│   ├── MoneyWithdrawnEvent.php
│   ├── MoneyAddedEvent.php
│   └── TransactionCancelledEvent.php
├── Listeners/
│   ├── ProcessMoneyWithdrawalListener.php
│   ├── ProcessMoneyAdditionListener.php
│   └── ProcessTransactionCancellationListener.php
├── Observers/
│   └── TransactionObserver.php
├── Services/
│   ├── WalletTransactionService.php
│   └── OrderService.php
└── Models/
    ├── Order.php (updated)
    ├── Transaction.php (updated)
    ├── User.php (updated)
    └── TopUpProvider.php (new)

database/
├── migrations/
│   ├── 2025_07_29_create_top_up_providers_table.php
│   └── 2025_07_29_update_orders_table_structure.php
└── seeders/
    └── TopUpProviderSeeder.php

tests/
├── Unit/
│   ├── WalletTransactionServiceTest.php
│   └── OrderServiceTest.php
├── Feature/
│   ├── WalletOperationsTest.php
│   └── AdminTopUpTest.php
└── Integration/
    └── WalletWorkflowTest.php
```

## Error Handling Strategy

### Service Layer Exceptions
- `InsufficientBalanceException`
- `InvalidOrderStatusException`
- `UnauthorizedOperationException`
- `WalletTransactionException`
- `InvalidTopUpProviderException`
- `MissingProviderReferenceException`
- `DuplicateProviderReferenceException`

### Transaction Safety
- All wallet operations wrapped in database transactions
- Rollback on any failure in the chain
- Proper exception propagation to controllers

## Security Considerations

1. **Authorization**: Verify user permissions for all operations
2. **Balance Validation**: Always check balance before withdrawals
3. **Audit Trail**: Complete transaction history with created_by tracking
4. **Concurrent Operations**: Use database transactions to prevent race conditions

## Performance Considerations

1. **Database Indexing**: Add indexes on frequently queried columns
2. **Balance Caching**: Consider caching user balances for read-heavy operations
3. **Batch Operations**: Support bulk transaction processing if needed

## Monitoring and Logging

1. **Transaction Logs**: Log all wallet operations for audit purposes
2. **Error Tracking**: Monitor exception rates and types
3. **Balance Verification**: Periodic balance reconciliation jobs

## Implementation Timeline

1. **Day 1**: Database migration and model updates
2. **Day 2**: WalletTransactionService implementation and tests
3. **Day 3**: OrderService implementation and tests
4. **Day 4**: Event system and observers implementation
5. **Day 5**: Integration tests and bug fixes
6. **Day 6**: Performance testing and optimization

## Success Criteria

1. All unit tests pass with >95% coverage
2. Feature tests cover all user scenarios including admin operations
3. No balance inconsistencies under concurrent load
4. Proper error handling and rollback in failure scenarios
5. Complete audit trail for all operations with provider tracking
6. Admin can create top-ups for any user with proper authorization
7. Provider reference validation works correctly
8. Performance benchmarks meet requirements
9. Full reconciliation capability through provider references

## Post-Implementation Tasks

1. Create API endpoints (separate feature)
2. Frontend integration (separate feature)
3. Admin reporting interfaces
4. Monitoring dashboard setup