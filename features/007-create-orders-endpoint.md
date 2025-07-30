# Create Orders Endpoint

Create the orders endpoint. It should allow CRUD operations for an order. Here are the rules:

- A user can create a top-up order for himself at any time
- Create an endpoint to return a list of the possible top-up providers
- The amount for the top-up has a limit of 10000
- Set the limit in the orders service, but also send it as an endpoint in an orders/rules GET request
- If the order is a transfer, the endpoint should receive a post request with the receiver's user_id as well
- Updates to an existing order are acceptable if it's not yet completed or cancelled
- An order can be cancelled only if it's pending
- Create an endpoint that returns a list of all the current user's orders, which receives as parameters filtering by date range, amount, status, and receiver user id

Follow the rules in CLAUDE.md - the controller should be thin and only trigger events, logic should stay in the orders service. Create OpenAPI annotations for the new endpoint.

Create the necessary tests for the endpoint.

## Enhancement History

### 2025-07-30: Automatic Order Description Generation

**Added feature**: Automatic generation of descriptive order descriptions based on order type and context.

**Implementation**:
- Added `generateOrderDescription()` method to `OrdersService`
- Descriptions include relevant information like:
  - Internal transfers: "Received funds from user@email.com to receiver@email.com"
  - User top-ups: "Order purchased funds #123 - User top-up by user@email.com" 
  - Admin top-ups: "Admin top-up for user@email.com - Order #123"
  - User withdrawals: "User withdrawal request by user@email.com - Order #123"
  - Custom descriptions take precedence when provided

**Integration**:
- Updated `createOrder()` method to use generated descriptions
- Updated `createAdminTopUp()` method to use generated descriptions
- Updated `createWithdrawalRequest()` method to use generated descriptions
- Descriptions include order ID after order creation for full context

**Testing**:
- Added comprehensive tests for description generation
- Tests cover all order types and custom description handling
- Verified integration with existing order creation workflows