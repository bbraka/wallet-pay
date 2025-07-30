# Orders CRUD Endpoint Implementation Plan

## Overview
Implement comprehensive CRUD operations for orders with support for top-ups and internal transfers, following Laravel event-driven architecture.

## Requirements Analysis
- Users can create top-up orders (limit: 10,000)
- Users can create transfer orders (requires receiver_user_id)
- Orders can be updated only if status is `pending_payment`
- Orders can be cancelled only if status is `pending_payment`
- List orders with filtering by date range, amount, status, receiver_user_id
- Provide top-up providers endpoint
- Provide validation rules endpoint

## Implementation Structure

### 1. Controllers (Thin Layer)
**File**: `app/Http/Controllers/Merchant/OrdersController.php`
- `index()` - List user's orders with filters
- `store()` - Create new order
- `show()` - Show specific order
- `update()` - Update existing order
- `destroy()` - Cancel order
- `rules()` - Get validation rules

**File**: `app/Http/Controllers/Merchant/TopUpProvidersController.php`
- `index()` - List active top-up providers

### 2. Services (Business Logic)
**File**: `app/Services/Merchant/OrdersService.php`
- `createOrder(array $data)` - Handle order creation logic
- `updateOrder(Order $order, array $data)` - Handle order updates
- `cancelOrder(Order $order)` - Handle order cancellation
- `getOrdersWithFilters(User $user, array $filters)` - Filtered listing
- `getValidationRules()` - Return validation rules including limits
- `validateOrderData(array $data)` - Comprehensive validation

### 3. API Routes
```php
Route::prefix('merchant')->middleware(CustomAuth::class)->group(function () {
    // Orders CRUD
    Route::get('/orders', [OrdersController::class, 'index']);
    Route::post('/orders', [OrdersController::class, 'store']);
    Route::get('/orders/rules', [OrdersController::class, 'rules']);
    Route::get('/orders/{order}', [OrdersController::class, 'show']);
    Route::put('/orders/{order}', [OrdersController::class, 'update']);
    Route::delete('/orders/{order}', [OrdersController::class, 'destroy']);
    
    // Top-up providers
    Route::get('/top-up-providers', [TopUpProvidersController::class, 'index']);
});
```

### 4. Business Rules Implementation

#### Order Creation Rules
- **Top-up Orders**: 
  - Require `top_up_provider_id`
  - Amount limit: `Order::MAX_TOP_UP_AMOUNT` (10,000)
  - Auto-set `order_type` to `USER_TOP_UP`
- **Transfer Orders**:
  - Require `receiver_user_id`
  - Amount limit: `Order::MAX_TRANSFER_AMOUNT` (5,000)
  - Validate receiver exists and is not the same as sender
  - Auto-set `order_type` to `INTERNAL_TRANSFER`

#### Order Update Rules
- Only allow updates if `status === 'pending_payment'`
- Prevent direct status changes to `completed` or `cancelled`
- Allow modification of `title`, `description`, `amount` (with limits)

#### Order Cancellation Rules
- Only allow if `status === 'pending_payment'`
- Set status to `cancelled`
- Trigger `OrderCancelled` event

### 5. Events & Listeners
**Events**:
- `App\Events\OrderCreated`
- `App\Events\OrderUpdated` 
- `App\Events\OrderCancelled`

**Listeners**:
- `App\Listeners\ProcessOrderCreation`
- `App\Listeners\LogOrderUpdate`
- `App\Listeners\ProcessOrderCancellation`

### 6. Validation & Request Classes
**File**: `app/Http/Requests/Merchant/CreateOrderRequest.php`
- Validation for order creation
- Custom rules for amount limits
- Conditional validation based on order type

**File**: `app/Http/Requests/Merchant/UpdateOrderRequest.php`
- Validation for order updates
- Status-based validation rules

**File**: `app/Http/Requests/Merchant/OrderIndexRequest.php`
- Validation for filtering parameters
- Date range validation

### 7. API Response Structure

#### Order Resource
```json
{
  "id": 1,
  "title": "Top-up via PayPal",
  "amount": "100.00",
  "status": "pending_payment",
  "order_type": "user_top_up",
  "description": "Wallet top-up",
  "user_id": 1,
  "receiver_user_id": null,
  "top_up_provider_id": 1,
  "provider_reference": null,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

#### Rules Endpoint Response
```json
{
  "max_top_up_amount": 10000,
  "max_transfer_amount": 5000,
  "required_fields": {
    "top_up": ["title", "amount", "top_up_provider_id"],
    "transfer": ["title", "amount", "receiver_user_id"]
  },
  "allowed_statuses": ["pending_payment", "completed", "cancelled", "refunded"]
}
```

### 8. Testing Strategy

#### Feature Tests
- `tests/Feature/Merchant/OrdersControllerTest.php`
  - Test all CRUD operations
  - Test filtering and pagination
  - Test authorization (users can only access own orders)

#### Unit Tests
- `tests/Unit/Services/Merchant/OrdersServiceTest.php`
  - Test business logic validation
  - Test amount limits
  - Test order type detection

#### Edge Cases
- Invalid receiver_user_id
- Exceeding amount limits
- Unauthorized access attempts
- Invalid status transitions

### 9. OpenAPI Annotations
- Complete Swagger documentation for all endpoints
- Request/response schemas
- Error response documentation
- Authentication requirements

### 10. Database Considerations
- Existing `orders` table structure is sufficient
- Leverage existing relationships and scopes
- Use soft deletes for audit trail

## Implementation Timeline
1. **Phase 1**: Core service and controller structure
2. **Phase 2**: Request validation and events
3. **Phase 3**: Comprehensive testing
4. **Phase 4**: OpenAPI documentation
5. **Phase 5**: Integration testing with frontend

## Success Criteria
- All CRUD operations work correctly
- Business rules are enforced
- Comprehensive test coverage (>90%)
- Full OpenAPI documentation
- Performance optimized queries
- Proper error handling and responses