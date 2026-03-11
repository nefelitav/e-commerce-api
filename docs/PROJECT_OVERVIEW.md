# Shop API - Project Overview

## Project Description

**Shop API** is a RESTful API built with Laravel 12 that provides a comprehensive e-commerce backend system. It manages products, categories, orders, inventory, coupons, and returns with advanced filtering, sorting, and pagination capabilities.

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12, PHP 8.2+ |
| Database | PostgreSQL 16 (Docker/prod), SQLite (local dev) |
| ORM | Eloquent with migrations |
| Cache & Queues | Redis 7 |
| Web Server | Nginx |
| Containerisation | Docker & Docker Compose |
| Testing | PHPUnit 11.5, Mockery 1.6 |
| Code Quality | Laravel Pint, PHPStan (level 7) via Larastan |
| IDE Support | Laravel IDE Helper |

---

## Key Features

### Product Management
- CRUD with filtering (name, category, price range, quantity range)
- Dedicated search endpoint (`GET /products/search?q=...`) across name and description
- Filter by multiple categories (`filter[category_ids]=1,3,7`)
- Sort by any field, paginated results with metadata
- Include related categories

### Category Management
- Hierarchical parent-child structure
- Filter by name or parent category
- List subcategories

### Order Management
- Multi-item orders with atomic stock validation
- Filter by status (single or multiple) and price range
- Full status lifecycle: pending → paid → processing → shipped → delivered
- Cancellation with auto-refund (restores stock for paid/processing orders)
- User cancellation within 24h window

### Inventory Management
- Pessimistic row-level locking (`SELECT ... FOR UPDATE`) prevents overselling
- Full audit trail with change types: addition, removal, sale, return, adjustment, transfer
- All stock mutations wrapped in database transactions

### Coupon System
- Percentage and fixed-amount discount types
- Validation: active status, expiry, usage limits, minimum order amount
- Discount preview via `POST /coupons/apply`

### Return/Refund System
- Customers submit return requests for qualifying orders (paid, shipped, or delivered)
- Admin approval/rejection workflow with notes
- Approved returns automatically restore inventory and transition order to refunded

### Webhooks
- **Inbound:** payment provider reports `paid` / `payment_failed`; shipping carrier reports `shipped` / `delivered`
- **Outbound:** `order.paid` event dispatches queued webhook to configurable URL
- HMAC-SHA256 signature verification

### Email Notifications
- Queued emails for order confirmation, payment received, and shipment notification
- Dispatched via `emails` queue with 3 retries and 30s backoff

### Caching
- Tagged cache for categories (30-min TTL) and products (5-min TTL)
- Automatic invalidation on write operations

### Audit Logging
- All mutations logged to dedicated `audit` channel with structured entries
- Separate daily log file with 90-day retention

### Advanced Querying
- Multi-field AND filtering, comma-separated OR filtering
- Cross-field search, configurable sorting, pagination with metadata
- Related resource includes

---

## Project Statistics

- **Controllers**: 25+ (across 7 resource domains + webhooks + admin)
- **Models**: 8 entities (User, Product, Category, Order, OrderItem, InventoryHistory, Coupon, ReturnRequest)
- **Repositories**: 6 (with interfaces)
- **Services**: 7 + AuditLogger
- **Tests**: 420+ (Unit, Feature, E2E, Performance, Security)
- **API Endpoints**: 30+

---

## Next Steps

- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) — Full endpoint reference
- [ARCHITECTURE.md](./ARCHITECTURE.md) — Design patterns, order state machine, data flow
- [SETUP_AND_DEVELOPMENT.md](./SETUP_AND_DEVELOPMENT.md) — Installation, Docker, testing, code standards
- [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) — Table structures and relationships
- [UML_DIAGRAMS.md](./UML_DIAGRAMS.md) — ERD, component, deployment, state & sequence diagrams
