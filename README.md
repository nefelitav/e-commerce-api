# E-commerce API

A comprehensive RESTful API for e-commerce built with **Laravel 12**, **PHP 8.2+**, **PostgreSQL 16**, **Redis 7**, **Nginx**, and **Docker**.

## 🚀 Quick Start

### With Docker Compose

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

The API will be available at `http://localhost:8081/api/v1/`

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1/`

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [PROJECT_OVERVIEW.md](./docs/PROJECT_OVERVIEW.md) | Features, goals & project statistics |
| [API_DOCUMENTATION.md](./docs/API_DOCUMENTATION.md) | Complete API reference (endpoints, filters, sorting, pagination) |
| [ARCHITECTURE.md](./docs/ARCHITECTURE.md) | Design patterns, request flow, order state machine, caching, webhooks, emails |
| [SETUP_AND_DEVELOPMENT.md](./docs/SETUP_AND_DEVELOPMENT.md) | Installation, Docker, testing, code standards, environment config |
| [DATABASE_SCHEMA.md](./docs/DATABASE_SCHEMA.md) | Table structures, relationships & indexes |
| [UML_DIAGRAMS.md](./docs/UML_DIAGRAMS.md) | ERD, component, deployment, state & sequence diagrams |

## 🎯 Highlights

- 30+ RESTful endpoints with advanced filtering, sorting & pagination
- Rate limiting, API versioning
- Request validation, standardized responses, comprehensive error handling
- Search engine
- Hexagonal architecture with CQRS command bus
- RBAC (Guest / User / Admin)
- Order state machine with webhook-driven payment & shipping
- Pessimistic locking for inventory mutations
- Coupon system (percentage & fixed amount) with validation
- Return/refund workflow with automatic stock restoration
- Tagged caching with automatic invalidation
- Event-driven emails & outbound webhooks (queued)
- Audit logging to dedicated channel
- Performance optimizations (eager loading, indexing, caching)
- Security best practices (input sanitization, prepared statements, auth & permissions)
- Full test coverage: 420+ tests (Unit, Feature, E2E, Performance, Security)
- Dockerized with PHP-FPM, Nginx, PostgreSQL & Redis
- CI/CD pipeline with GitHub Actions (linting, testing, code coverage, security checks)
- Detailed documentation

## 🚀 Quick API Examples

```bash
# Search products with filters
GET /api/v1/products/search?q=wireless&filter[min_price]=50&filter[max_price]=300&sort=price&order=asc

# Place an order (validates stock with pessimistic locking)
POST /api/v1/orders
{ "status": "pending", "total_price": 259.98, "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 99.99 },
    { "product_id": 5, "quantity": 1, "unit_price": 60.00 }
]}

# Preview coupon discount
POST /api/v1/coupons/apply
{ "code": "SUMMER25", "order_total": 259.98 }

# Payment webhook → triggers emails & outbound webhooks
POST /api/v1/webhooks/payments
{ "order_id": 42, "payment_reference": "pay_abc123", "status": "paid" }

# Shipping carrier webhook
POST /api/v1/webhooks/shipping
{ "order_id": 42, "event": "shipped", "tracking_number": "1Z999AA10123456784" }

# Cancel within 24h (auto-refund + stock restored)
PUT /api/v1/orders/42
{ "status": "cancelled", "total_price": 259.98 }
```

> Full endpoint reference with all parameters, responses, and examples: **[docs/API_DOCUMENTATION.md](./docs/API_DOCUMENTATION.md)**

## 📦 Project Structure

```
app/
├── CQRS/                   # Command Bus (Commands & Handlers)
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
tests/
├── E2E/                    # End-to-end tests
├── Feature/                # Feature tests
├── Performance/            # Performance tests
├── Security/               # Security tests
└── Unit/                   # Unit tests
```

## 📄 License

This project is open-sourced software licensed under the [Apache 2.0 license](https://www.apache.org/licenses/LICENSE-2.0).
