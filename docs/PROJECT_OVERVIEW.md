# Shop API - Project Overview

## 📋 Table of Contents
1. [Project Description](#project-description)
2. [Technology Stack](#technology-stack)
3. [Project Goals](#project-goals)
4. [Architecture Overview](#architecture-overview)
5. [Key Features](#key-features)
6. [File Structure](#file-structure)
7. [Getting Started](#getting-started)

---

## Project Description

**Shop API** is a RESTful API built with Laravel 12 that provides a comprehensive e-commerce backend system. It manages products, categories, orders, and inventory with advanced filtering, sorting, and pagination capabilities.

### Primary Purpose
- Provide a robust backend for e-commerce applications
- Handle product catalog management
- Process orders and maintain inventory
- Support complex queries with filtering and sorting

### Target Users
- Frontend developers integrating with the API
- Mobile app developers
- Third-party integrations
- Admin dashboard applications

---

## Technology Stack

### Backend Framework
- **Laravel 12.0** - PHP web framework
- **PHP 8.2+** - Programming language

### Database
- **PostgreSQL 16** (Docker / Production)
- **SQLite** (Local development without Docker)
- **Eloquent ORM** - Database abstraction layer
- **Migrations** - Database versioning

### API Documentation
- **L5 Swagger** - Swagger/OpenAPI documentation

### Development Tools
- **PHPUnit 11.5** - Testing framework
- **Mockery 1.6** - Mock objects
- **Laravel Pint** - PHP code style fixer
- **PHPStan** - Static analysis
- **Larastan** - Laravel PHPStan integration

### Code Quality
- **PHPStan** - Static type analysis
- **Laravel IDE Helper** - IDE autocompletion
- **PHP CS Fixer** - Code formatting

---

## Architecture Overview

### Design Patterns Used

#### 1. **Repository Pattern**
- Abstracts database access
- Provides consistent interface for data operations
- Located in: `app/Repositories/`

#### 2. **Service Layer Pattern**
- Contains business logic
- Orchestrates repository operations
- Located in: `app/Services/`

#### 3. **Data Transfer Objects (DTOs)**
- Encapsulate data structures
- Provide type safety
- Located in: `app/Dto/`

#### 4. **Request/Response Pattern**
- Validates input (Requests)
- Standardizes output (Responses)
- Located in: `app/Http/Requests/` and `app/Http/Responses/`

#### 5. **Transformer Pattern**
- Converts DTOs to API response format
- Located in: `app/Transformers/`

#### 6. **CQRS Command Bus Pattern**
- Decouples write intent from execution
- Immutable command value objects
- Thin handlers delegate to service layer
- Located in: `app/CQRS/`

### Layered Architecture

```
┌─────────────────────────────┐
│    HTTP Layer               │
│  (Controllers & Routes)     │
├─────────────────────────────┤
│    Request Validation       │
│  (Form Requests)            │
├─────────────────────────────┤
│    Business Logic           │
│  (Services)                 │
├─────────────────────────────┤
│    Data Access              │
│  (Repositories)             │
├─────────────────────────────┤
│    Database Layer           │
│  (Eloquent Models)          │
├─────────────────────────────┤
│    Response Formatting      │
│  (Transformers)             │
└─────────────────────────────┘
```

---

## Key Features

### 1. **Product Management**
- List products with filtering (name, category, price range, quantity range)
- Search across name and description (`filter[search]`)
- Dedicated search endpoint (`GET /products/search?q=...`)
- Filter by multiple categories at once (`filter[category_ids]`)
- Sort by any field (id, name, price, quantity, created_at, updated_at)
- Paginated results with metadata
- Include related categories

### 2. **Category Management**
- Hierarchical categories (parent-child relationships)
- Filter by name or parent category
- List subcategories
- Full pagination support

### 3. **Order Management**
- Create and manage orders
- Filter by status (single or multiple: `filter[status]=pending,paid`)
- Filter by price range
- Track order items
- Pagination support

### 4. **Inventory Management**
- Track product quantities
- Maintain inventory history (additions and adjustments)
- Pessimistic row-level locking (`SELECT ... FOR UPDATE`) prevents race conditions and overselling during concurrent stock updates
- `InsufficientStockException` thrown when quantity would go negative
- All stock mutations are wrapped in database transactions for atomicity

### 5. **Coupon/Discount System**
- Create and manage discount coupons (admin)
- Support for percentage and fixed-amount coupon types
- Coupon validation: active status, expiry date, usage limits, minimum order amount
- Discount calculation preview via `POST /coupons/apply`
- Coupons linked to orders via `coupon_id` and `discount_amount` fields
- Full CRUD management for admins

### 6. **Return/Refund System**
- Customers can submit return requests for qualifying orders (paid, shipped, or delivered)
- Admin approval/rejection workflow with notes
- Approved returns automatically restore inventory (stock returned to products)
- Approved returns transition the order to `refunded` status
- One pending/approved return request per order
- Full audit trail for return request lifecycle

### 7. **Advanced Querying**
- Multi-field filtering with AND logic
- OR filtering via comma-separated values (`filter[category_ids]=1,3,7`, `filter[status]=pending,paid`)
- Cross-field OR search (`filter[search]=laptop` matches name or description)
- Sort by any relevant field
- Pagination with configurable page size
- Load related resources via includes

### 8. **Caching**
- Tagged cache for categories (30-min TTL) and products (5-min TTL)
- Automatic cache invalidation on create, update, and delete operations
- Order placement invalidates product cache to reflect stock changes
- Cache keys derived from query parameters via `md5(serialize())` for list endpoints
- Configurable cache driver via `CACHE_STORE` env var (`array` for dev, `redis` for production)

### 9. **Audit Logging**
- All create, update, and delete operations logged to dedicated `audit` channel
- Structured log entries with entity, action, user, properties, IP, and timestamp
- Separate daily log file (`storage/logs/audit.log`) with 90-day retention
- Logged at the service layer — only successful mutations are recorded

### 10. **Webhooks**
- Inbound: `POST /api/v1/webhooks/payments` receives payment confirmations from external providers
- Payment confirmation transitions orders from `pending` to `paid` via `markOrderAsPaid()`
- Outbound: `order.paid` event dispatches queued webhook to configurable URL
- 3 retries with 10s backoff on outbound failure
- Outbound URL configured via `WEBHOOK_ORDER_PAID_URL` env var (disabled when unset)

**Payment flow:**
```
Customer → places order → status: PENDING
                |
                ▼
        Pays on Stripe/PayPal
                |
                ▼
Stripe calls YOUR app ──────── INBOUND webhook
(POST /webhooks/payments)      (receiving a call)
                |
                ▼
       Order → status: PAID
                |
                ▼
YOUR app calls warehouse ───── OUTBOUND webhook
(POST to fulfillment URL)      (sending a call)
                |
                ▼
       Admin ships it → status: SHIPPED → DELIVERED
```

### 11. **Email Notifications**
- Queued email notifications for key order lifecycle events
- Order confirmation email sent on order placement
- Payment received email sent when payment is confirmed
- Shipment notification email sent when order ships
- All emails dispatched via the `emails` queue with 3 retries and 30s backoff
- Default mailer set to `log` for development (configure SMTP for production)

### 12. **Standardized Responses**
- Consistent JSON format
- Pagination metadata
- Success/error indicators
- Helpful messages

### 13. **Request Validation**
- Validate all input parameters
- Type checking (integer, string, numeric)
- Range validation
- Database existence validation

---

## File Structure

### Core Application

```
app/
├── CQRS/                             # Command Bus Pattern
│   ├── CommandBus.php
│   ├── Commands/
│   │   ├── CommandInterface.php
│   │   ├── Order/
│   │   │   ├── CreateOrderCommand.php
│   │   │   └── CreateOrderCommandItem.php
│   │   └── Product/
│   │       └── CreateProductCommand.php
│   └── Handlers/
│       ├── CommandHandlerInterface.php
│       ├── Order/
│       │   └── CreateOrderCommandHandler.php
│       └── Product/
│           └── CreateProductCommandHandler.php
├── Dto/                          # Data Transfer Objects
│   ├── Category/
│   ├── Coupon/
│   ├── InventoryHistory/
│   ├── Order/
│   ├── Product/
│   └── ReturnRequest/
├── Enums/                        # Enumerations
│   ├── CouponType.php
│   ├── InventoryChangeType.php
│   ├── OrderStatus.php
│   └── ReturnRequestStatus.php
├── Events/                       # Domain Events
│   ├── OrderCreatedEvent.php
│   ├── OrderPaidEvent.php
│   └── OrderShippedEvent.php
├── Exceptions/                   # Custom Exceptions
│   ├── BadRequestException.php
│   ├── CategoryAlreadyExistsException.php
│   ├── CategoryNotFoundException.php
│   ├── Handler.php
│   ├── InsufficientStockException.php
│   ├── InvalidOrderStateException.php
│   ├── OrderNotFoundException.php
│   ├── ProductAlreadyExistsException.php
│   ├── ProductNotFoundException.php
│   └── UnprocessableEntityException.php
├── Listeners/                    # Event Listeners
│   ├── SendOrderConfirmationEmail.php
│   ├── SendOrderPaidEmail.php
│   ├── SendOrderPaidWebhook.php
│   └── SendOrderShippedEmail.php
├── Mail/                         # Queued Mailables
│   ├── OrderConfirmationMail.php
│   ├── OrderPaidMail.php
│   └── OrderShippedMail.php
├── Http/
│   ├── Controllers/              # API Controllers
│   │   └── Api/V1/
│   │       ├── Admin/
│   │       │   ├── Order/
│   │       │   └── Product/
│   │       ├── Category/
│   │       ├── Coupon/
│   │       ├── InventoryHistory/
│   │       ├── Order/
│   │       ├── Product/
│   │       ├── ReturnRequest/
│   │       └── Webhook/
│   ├── Middleware/                # Custom Middleware
│   │   ├── RequireAdmin.php
│   │   └── RequireAuth.php
│   ├── Requests/                 # Form Requests (Validation)
│   │   ├── Admin/
│   │   │   ├── Order/
│   │   │   └── Product/
│   │   ├── Category/
│   │   ├── Coupon/
│   │   ├── InventoryHistory/
│   │   ├── Order/
│   │   ├── Product/
│   │   ├── ReturnRequest/
│   │   └── Webhook/
│   └── Responses/                # Response Objects
│       ├── ApiResponse.php
│       ├── ArrayableResponse.php
│       ├── Category/
│       ├── Coupon/
│       ├── InventoryHistory/
│       ├── Order/
│       ├── Product/
│       ├── ReturnRequest/
│       └── Webhook/
├── Models/                       # Eloquent Models
│   ├── CreatedAtUtcTrait.php
│   ├── UpdatedAtUtcTrait.php
│   ├── UserModel.php
│   ├── Category/
│   ├── Coupon/
│   ├── InventoryHistory/
│   ├── Order/
│   ├── Product/
│   └── ReturnRequest/
├── Repositories/                 # Data Access Layer (interface + implementation per domain)
│   ├── Category/
│   ├── Coupon/
│   ├── InventoryHistory/
│   ├── Order/
│   ├── Product/
│   └── ReturnRequest/
├── Services/                     # Business Logic Layer (interface + implementation per domain)
│   ├── AuditLogger.php
│   ├── Category/
│   ├── Coupon/
│   ├── InventoryHistory/
│   ├── Order/
│   ├── Product/
│   └── ReturnRequest/
├── Transformers/                 # Response Transformation
│   ├── CategoryTransformer.php
│   ├── CouponTransformer.php
│   ├── InventoryHistoryTransformer.php
│   ├── OrderTransformer.php
│   ├── ProductTransformer.php
│   └── ReturnRequestTransformer.php
└── Providers/
    └── AppServiceProvider.php

routes/
├── api.php                       # API Routes
├── console.php
└── web.php

tests/
├── E2E/                          # End-to-End Tests (full user scenarios)
├── Feature/                      # Feature Tests
│   ├── Controllers/
│   └── Middleware/
├── Performance/                  # Performance & Throughput Tests
├── Security/                     # Security & Vulnerability Tests
├── Unit/                         # Unit Tests
│   ├── CQRS/
│   ├── Events/
│   ├── Listeners/
│   ├── Repositories/
│   └── Services/
├── DataProviders/                # Shared test data providers
├── Fixtures/                     # Test fixtures (CatalogFixture, OrderFixture, UserFixture)
├── Traits/                       # Test traits (InteractsWithShopApi, MeasuresPerformance)
└── TestCase.php

database/
├── migrations/                   # Database Migrations
├── seeders/
└── factories/                    # Model Factories

config/                           # Configuration Files
├── app.php
├── auth.php
├── cache.php
├── database.php
├── l5-swagger.php
├── logging.php
├── webhooks.php
└── ...

storage/
├── api-docs/                     # Generated Swagger Docs
├── app/
├── framework/
└── logs/
```

---

## Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and npm
- SQLite (included)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd shop-api
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup database**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Install Node dependencies**
   ```bash
   npm install
   npm run build
   ```

6. **Generate API documentation**
   ```bash
   php artisan l5-swagger:generate
   ```

7. **Start development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000/api/v1/`

---

## Project Statistics

- **Controllers**: 25+ (CRUD operations across 7 resource domains + webhooks + admin)
- **Models**: 8 entities (UserModel, ProductModel, CategoryModel, OrderModel, OrderItemModel, InventoryHistoryModel, CouponModel, ReturnRequestModel)
- **Repositories**: 6 (with interfaces: Product, Category, Order, InventoryHistory, Coupon, ReturnRequest)
- **Services**: 7 (Product, Category, Order, InventoryHistory, Coupon, ReturnRequest, AuditLogger)
- **Tests**: 350+ (Unit, Feature, E2E, Performance, and Security tests)
- **API Endpoints**: 30+ RESTful endpoints
- **Lines of Code**: 4000+

---

## Next Steps

For detailed documentation, see:
- [API DOCUMENTATION](./API_DOCUMENTATION.md) - Full API endpoint reference
- [SETUP AND DEVELOPMENT](./SETUP_AND_DEVELOPMENT.md) - Detailed setup and development instructions
- [ARCHITECTURE](./ARCHITECTURE.md) - Detailed architecture guide
- [DATABASE SCHEMA](./DATABASE_SCHEMA.md) - Database structure

