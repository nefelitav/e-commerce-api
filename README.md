# Shop API

A comprehensive RESTful API for e-commerce built with Laravel 12.

## 🚀 Quick Start

### With Docker Compose

```bash
# Start all services (app, queue worker, Nginx, PostgreSQL, Redis)
docker compose up -d

# Run migrations and seed the database
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

The API will be available at `http://localhost:8081/api/v1/`

### Without Docker

```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database
touch database/database.sqlite
php artisan migrate
php artisan db:seed

# Start server
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1/`

## 📚 Documentation

Complete documentation is available in the `docs/` directory:

- **[docs/PROJECT_OVERVIEW.md](./docs/PROJECT_OVERVIEW.md)** - Project description and goals
- **[docs/API_DOCUMENTATION.md](./docs/API_DOCUMENTATION.md)** - Complete API reference
- **[docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md)** - Design patterns and architecture
- **[docs/SETUP_AND_DEVELOPMENT.md](./docs/SETUP_AND_DEVELOPMENT.md)** - Setup and development guide
- **[docs/DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md)** - Database schema documentation
- **[docs/UML_DIAGRAMS.md](./docs/UML_DIAGRAMS.md)** - UML diagrams (ERD, component, deployment, state & sequence)

## 🎯 Features

- ✅ RESTful API with 30+ endpoints
- ✅ Advanced filtering and sorting
- ✅ Pagination with metadata
- ✅ Rate limiting
- ✅ API versioning
- ✅ RBAC
- ✅ Product search
- ✅ Request validation
- ✅ Standardized responses
- ✅ Comprehensive error handling
- ✅ Hexagonal architecture
- ✅ Full test coverage
- ✅ Caching
- ✅ Event-driven architecture with domain events and listeners
- ✅ Pessimistic locking for inventory mutations
- ✅ Webhooks for external integrations
- ✅ Inventory audit trail
- ✅ State engines for orders
- ✅ Dockerized development environment
- ✅ Detailed documentation
- ✅ CI/CD ready
- ✅ Performance optimizations (eager loading, indexing, caching)
- ✅ Security best practices (input sanitization, prepared statements, auth & permissions)


## 🔧 Technology Stack

- **Laravel 12** - PHP Framework
- **PHP 8.2+** - Programming Language
- **PostgreSQL 16** - Primary Database
- **Redis 7** - Caching & Queues
- **Nginx** - Web Server
- **Docker** - Containerisation
- **PHPUnit** - Testing Framework

## 📋 API Resources

### Products
- List products with filters (name, category, price, quantity)
- Full-text search across products
- Sort by any field
- Include category relationships
- Pagination support
- Admin: CRUD operations & inventory history

### Categories
- Hierarchical structure (parent-child)
- Filter by parent category
- List subcategories
- Admin: full CRUD operations

### Orders
- Create orders with multiple items (stock validation & pessimistic locking)
- Filter by status or price range
- Track order items
- User-specific orders
- Status lifecycle: pending → paid → processing → shipped → delivered
- Cancellation with auto-refund (restores stock when cancelling paid/processing orders)
- User cancellation within 24h window from pending, payment_failed, or paid

### Coupons
- Apply coupon codes to orders (percentage or fixed amount)
- Validates min order amount, max uses, and expiry
- Returns discount breakdown (original total, discount, final total)
- Admin: full CRUD operations

### Return Requests
- Submit return requests for orders
- Track return status (pending → approved / rejected)
- Approval automatically refunds the order and restores stock
- Admin: approve or reject with notes

### Inventory History
- Full audit trail of stock changes
- Change types: addition, removal, sale, return, adjustment, transfer
- Product-specific history (admin only)

### Webhooks
- **Payment webhook** (`POST /api/v1/webhooks/payments`): payment provider reports `paid` or `payment_failed`
- **Shipping webhook** (`POST /api/v1/webhooks/shipping`): shipping carrier reports `shipped` or `delivered`
- HMAC-SHA256 signature verification
- Triggers event-driven notifications (emails, outbound webhooks)

## 🚀 Quick API Examples

```bash
# Search products by keyword with price range filter
GET /api/v1/products/search?q=wireless&filter[min_price]=50&filter[max_price]=300&sort=price&order=asc

# List products across multiple categories, sorted by newest
GET /api/v1/products?filter[category_ids]=1,3,5&sort=created_at&order=desc

# Get a category's subcategories
GET /api/v1/categories/1/subcategories

# Create an order with multiple items (validates stock with pessimistic locking)
POST /api/v1/orders
{
  "status": "pending",
  "total_price": 259.98,
  "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 99.99 },
    { "product_id": 5, "quantity": 1, "unit_price": 60.00 }
  ]
}

# Apply a coupon code and preview the discount breakdown
POST /api/v1/coupons/apply
{ "code": "SUMMER25", "order_total": 259.98 }
# → { "discount_amount": 64.99, "original_total": 259.98, "final_total": 194.99 }

# Submit a return request
POST /api/v1/return-requests
{ "order_id": 42, "reason": "Product arrived damaged" }

# Admin: approve a return request
POST /api/v1/return-requests/7/approve

# Process a payment webhook (external provider callback)
POST /api/v1/webhooks/payments
{ "order_id": 42, "payment_reference": "pay_abc123", "status": "paid" }

# Payment failed webhook (retry later)
POST /api/v1/webhooks/payments
{ "order_id": 42, "payment_reference": "pay_abc123", "status": "payment_failed" }

# Admin: start order fulfilment (paid → processing)
PUT /api/v1/orders/42
{ "status": "processing", "total_price": 259.98 }

# Shipping carrier webhook: order shipped
POST /api/v1/webhooks/shipping
{ "order_id": 42, "event": "shipped", "tracking_number": "1Z999AA10123456784" }

# Shipping carrier webhook: order delivered
POST /api/v1/webhooks/shipping
{ "order_id": 42, "event": "delivered" }

# User: cancel a paid order within 24h (auto-refund + stock restored)
PUT /api/v1/orders/42
{ "status": "cancelled", "total_price": 259.98 }

# Admin: view inventory audit trail for a product
GET /api/v1/products/1/inventory-history

# List pending orders sorted by most recent
GET /api/v1/orders?filter[status]=pending&sort=created_at&order=desc
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Controllers/Product/ListProductsControllerTest.php

# Run with coverage
php artisan test --coverage
```

## 📦 Project Structure

```
app/
├── CQRS/                   # Command Query Responsibility Segregation
│   ├── Commands/           # Command definitions (Order, Product)
│   └── Handlers/           # Command handlers
├── Dto/                    # Data Transfer Objects
├── Enums/                  # Enums (CouponType, OrderStatus, etc.)
├── Events/                 # Domain events (OrderCreated, OrderPaid, etc.)
├── Listeners/              #   ↳ Event listeners (emails, webhooks)
├── Mail/                   #   ↳ Mailable templates used by listeners
├── Exceptions/             # Custom exception classes
├── Http/
│   ├── Controllers/        # API Controllers
│   ├── Middleware/         # Auth & admin middleware
│   ├── Requests/           # Request Validation
│   └── Responses/          # Response Objects
├── Models/                 # Eloquent Models
├── Providers/              # Service Providers
├── Repositories/           # Data Access Layer
├── Services/               # Business Logic Layer
└── Transformers/           # Response Transformation

config/                     # Application configuration

database/
├── factories/              # Model factories
├── migrations/             # Database migrations
└── seeders/                # Database seeders

docker/                     # Docker configuration (Nginx, PHP)
docs/                       # Complete documentation
routes/api.php              # API route definitions

tests/
├── DataProviders/          # Shared test data providers
├── E2E/                    # End-to-end tests
├── Feature/                # Feature tests
├── Fixtures/               # Test fixtures
├── Performance/            # Performance tests
├── Security/               # Security tests
├── Traits/                 # Shared test traits
└── Unit/                   # Unit tests
```

## 📄 License

This project is open-sourced software licensed under the [Apache 2.0 license](https://www.apache.org/licenses/LICENSE-2.0).
