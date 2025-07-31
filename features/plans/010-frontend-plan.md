# Frontend Implementation Plan - React Merchant Dashboard

## Overview
Create a complete React-based merchant dashboard with wallet functionality, following the Yii2 template design principles but with modern React patterns and Bootstrap 4 styling.

## Phase 1: Authentication & Navigation

### 1.1 Login Page
- **Location**: `resources/js/merchant/components/auth/LoginPage.jsx`
- **Features**:
  - Clean, centered login form with app branding
  - Email/password fields with validation
  - Loading states and error handling  
  - Responsive design with Bootstrap 4
  - Integration with `/api/merchant/login` endpoint
  - Redirect to wallet page on successful auth

### 1.2 Authentication Service
- **Location**: `resources/js/merchant/services/authService.js`
- **Features**:
  - Token management (localStorage)
  - API interceptor for auth headers
  - Logout functionality
  - Auto-redirect on token expiry

### 1.3 Navigation Layout
- **Location**: `resources/js/merchant/components/layout/Layout.jsx`
- **Features**:
  - Top navigation bar with profile menu
  - User name and email display
  - Logout option
  - Mobile-responsive hamburger menu

## Phase 2: Wallet Dashboard

### 2.1 Main Wallet Page
- **Location**: `resources/js/merchant/components/wallet/WalletPage.jsx`
- **Features**:
  - Wallet balance display (prominent, centered)
  - Quick action buttons (Top-up, Transfer, Withdraw)
  - Transaction history sections (Income/Expense)
  - Responsive card-based layout

### 2.2 Transaction List Component
- **Location**: `resources/js/merchant/components/wallet/TransactionList.jsx`
- **Features**:
  - Tabular transaction display
  - Date, amount, status, description columns
  - Pagination (Bootstrap pagination)
  - Mobile-responsive (collapsible columns)
  - Filtering by type (credit/debit)

### 2.3 Balance Display Component
- **Location**: `resources/js/merchant/components/wallet/BalanceCard.jsx`
- **Features**:
  - Large, prominent balance display
  - Currency formatting
  - Wallet icon/visual element
  - Action buttons below balance

## Phase 3: Transaction Operations

### 3.1 Top-up Order Page
- **Location**: `resources/js/merchant/components/orders/TopUpPage.jsx`
- **Features**:
  - Amount input with validation
  - Provider selection dropdown (from `/api/merchant/top-up-providers`)
  - Reference number field (conditional based on provider)
  - Form validation and submission to `/api/merchant/orders`
  - Success/error feedback

### 3.2 Internal Transfer Page
- **Location**: `resources/js/merchant/components/transfers/TransferPage.jsx`
- **Features**:
  - Recipient user search/selection
  - Amount input with balance validation
  - Transfer description field
  - Confirmation dialog
  - Integration with orders endpoint (internal_transfer type)

### 3.3 Withdrawal Request Page
- **Location**: `resources/js/merchant/components/withdrawals/WithdrawalPage.jsx`
- **Features**:
  - Amount input (with minimum/maximum validation)
  - Bank account details form
  - Withdrawal reason/description
  - Terms acceptance checkbox
  - Integration with `/api/merchant/orders/withdrawal`

## Phase 4: OpenAPI Client Generation & Services

### 4.1 OpenAPI TypeScript Client Generation
- **Library**: Official **OpenAPI Generator** (OpenAPITools/openapi-generator)
- **Generator**: `typescript-fetch` (perfect for React/browser applications)
- **Location**: Generated clients in `resources/js/merchant/generated/`
- **Features**:
  - Auto-generated TypeScript interfaces from OpenAPI schema
  - Type-safe API client methods with fetch-based implementation
  - Request/response type validation
  - ES6-compliant code generation
  - Automatic client regeneration on schema changes

### 4.2 Client Generation Configuration
- **Command**: `openapi-generator-cli generate -i /api/schema -g typescript-fetch -o resources/js/merchant/generated`
- **Script**: `npm run generate:api` in package.json
- **Config**: `.openapi-generator-ignore` for excluding files
- **Source**: `/api/schema` endpoint (existing Laravel OpenAPI schema)
- **Output**: 
  - `resources/js/merchant/generated/api.ts` - Main API classes
  - `resources/js/merchant/generated/models/` - TypeScript model interfaces
  - `resources/js/merchant/generated/runtime.ts` - Fetch runtime utilities

### 4.3 API Service Layer
- **Location**: `resources/js/merchant/services/apiService.js`
- **Features**:
  - Wrapper around generated OpenAPI client
  - Authentication token injection via Configuration
  - Request/response interceptors
  - Error handling and formatting
  - TypeScript integration with generated types

### 4.4 Reusable Form Components
- **Location**: `resources/js/merchant/components/forms/`
- **Components**:
  - `FormInput.jsx` - Styled input with validation
  - `FormSelect.jsx` - Dropdown with search
  - `FormButton.jsx` - Consistent button styling
  - `AmountInput.jsx` - Currency-formatted input

### 4.5 UI Components
- **Location**: `resources/js/merchant/components/ui/`
- **Components**:
  - `LoadingSpinner.jsx` - Loading states
  - `Alert.jsx` - Success/error messages
  - `Modal.jsx` - Confirmation dialogs
  - `Card.jsx` - Consistent card styling

## Phase 5: Routing & State Management

### 5.1 React Router Setup
- **Location**: `resources/js/merchant/App.jsx`
- **Routes**:
  - `/login` - Login page
  - `/wallet` - Main dashboard
  - `/topup` - Top-up page
  - `/transfer` - Transfer page  
  - `/withdraw` - Withdrawal page
  - Protected route wrapper for auth

### 5.2 State Management
- **Approach**: React Context + useReducer
- **Location**: `resources/js/merchant/context/`
- **Contexts**:
  - `AuthContext.js` - User auth state
  - `WalletContext.js` - Balance and transactions
  - `AppContext.js` - Global app state

## Phase 6: Styling & Responsive Design

### 6.1 SCSS Architecture
- **Location**: `resources/sass/merchant/`
- **Files**:
  - `_variables.scss` - Custom Bootstrap variables
  - `_components.scss` - Component-specific styles
  - `_layout.scss` - Layout and grid customizations
  - `merchant.scss` - Main stylesheet

### 6.2 Bootstrap 4 Customization
- **Features**:
  - Custom color scheme matching design template
  - Responsive breakpoints
  - Custom form styling
  - Card and button customizations

## Phase 7: Data Integration

### 7.1 API Integration Points
- **Authentication**: `/api/merchant/login`, `/api/merchant/logout`
- **User Data**: `/api/merchant/user`
- **Transactions**: `/api/merchant/orders` (GET for history)
- **Operations**: `/api/merchant/orders` (POST for new orders)
- **Providers**: `/api/merchant/top-up-providers`

### 7.2 Data Formatting
- **Currency**: Consistent formatting across components
- **Dates**: Localized date/time display
- **Status**: Human-readable status translations
- **Validation**: Client-side validation matching API rules

## Implementation Order

1. **Week 1**: Authentication system and basic layout
2. **Week 2**: Main wallet dashboard and transaction display
3. **Week 3**: Top-up and transfer functionality
4. **Week 4**: Withdrawal system and form components
5. **Week 5**: Styling, responsive design, and testing
6. **Week 6**: Integration testing and bug fixes

## Design Principles

### Modern React Patterns
- Functional components with hooks
- Custom hooks for business logic
- Component composition over inheritance
- TypeScript for type safety

### User Experience
- Loading states for all async operations
- Clear error messages and validation
- Responsive design (mobile-first)
- Accessible form controls and navigation

### Code Quality
- ESLint and Prettier for consistency
- Component testing with React Testing Library
- Storybook for component documentation
- PropTypes or TypeScript for type checking

## Testing Strategy

### Unit Tests
- Component rendering and props
- Business logic in custom hooks
- API service functions
- Form validation logic

### Integration Tests
- Full user flows (login → transaction)
- API integration tests
- Router navigation tests
- State management tests

### E2E Tests
- Laravel Dusk tests for complete workflows
- Cross-browser compatibility testing
- Mobile responsiveness testing
- Performance testing

## Dependencies

### Core React Stack
- React 18+ with hooks
- React Router v6
- React Query (TanStack Query) for server state management
- Generated TypeScript client (OpenAPI Generator with typescript-fetch)

### UI & Styling
- Bootstrap 4 (already in project)
- React Bootstrap components
- SCSS for styling
- React Icons for iconography

### Development Tools
- Vite for bundling (already configured)
- OpenAPI Generator CLI for client generation
- ESLint + Prettier
- React DevTools
- Chrome DevTools

## File Structure

```
resources/js/merchant/
├── generated/                 # Auto-generated OpenAPI client
│   ├── api.ts                # Main API classes
│   ├── models/               # TypeScript interfaces
│   └── runtime.ts            # Fetch utilities
├── components/
│   ├── auth/
│   │   └── LoginPage.jsx
│   ├── layout/
│   │   ├── Layout.jsx
│   │   ├── Header.jsx
│   │   └── Navigation.jsx
│   ├── wallet/
│   │   ├── WalletPage.jsx
│   │   ├── BalanceCard.jsx
│   │   └── TransactionList.jsx
│   ├── orders/
│   │   └── TopUpPage.jsx
│   ├── transfers/
│   │   └── TransferPage.jsx
│   ├── withdrawals/
│   │   └── WithdrawalPage.jsx
│   ├── forms/
│   │   ├── FormInput.jsx
│   │   ├── FormSelect.jsx
│   │   ├── FormButton.jsx
│   │   └── AmountInput.jsx
│   └── ui/
│       ├── LoadingSpinner.jsx
│       ├── Alert.jsx
│       ├── Modal.jsx
│       └── Card.jsx
├── services/
│   ├── apiService.js
│   └── authService.js
├── context/
│   ├── AuthContext.js
│   ├── WalletContext.js
│   └── AppContext.js
├── hooks/
│   ├── useAuth.js
│   ├── useApi.js
│   └── useWallet.js
├── utils/
│   ├── formatters.js
│   ├── validators.js
│   └── constants.js
└── App.jsx
```

## Success Criteria

1. **Functional**: All wallet operations work correctly
2. **Responsive**: Works on mobile, tablet, and desktop
3. **Accessible**: Meets WCAG 2.1 AA standards
4. **Performance**: Fast loading and smooth interactions
5. **Tested**: 90%+ test coverage with passing E2E tests
6. **Maintainable**: Clean, documented, and extensible code

## Notes

- Follow existing Laravel/PHP naming conventions for API integration
- Use Bootstrap 4 utility classes for consistent spacing
- Implement proper error boundaries for React error handling
- Consider implementing offline functionality for better UX
- Plan for internationalization (i18n) if multi-language support needed