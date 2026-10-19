# Shop API - Architecture Guide

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Design Patterns](#design-patterns)
3. [Request Flow](#request-flow)
4. [RBAC & Middleware](#rbac--middleware)
5. [Order Placement & Inventory](#order-placement--inventory)
6. [Order Status Machine](#order-status-machine)
7. [Caching](#caching)
8. [Audit Logging](#audit-logging)
9. [Webhooks](#webhooks)
10. [Email Notifications](#email-notifications)
11. [Error Handling](#error-handling)

---

## Architecture Overview

The Shop API follows a **layered hexagonal architecture** with clear separation of concerns:

```
HTTP Request
  → Route Dispatch (routes/api.php)
    → Controller (Api/V1/[Resource]/[Action]Controller)
      → Request Validation (Http/Requests/[Resource]/...)
        → Service Layer (Services/[Resource]/[Resource]Service)  ← business logic
          → Repository Layer (Repositories/[Resource]/...)       ← data access
            → Model / Eloquent ORM → Database
      ← Transformer (Transformers/[Resource]Transformer)         ← DTO → array
    ← Response Object (Http/Responses/[Resource]/...)            ← standardised JSON
  ← HTTP Response
```

---

## Design Patterns

| Pattern | Purpose | Location |
|---------|---------|----------|
| Repository | Abstract data access behind interfaces (bound in `AppServiceProvider`) | `app/Repositories/[Resource]/` |
| Service Layer | Encapsulate business logic | `app/Services/[Resource]/` |
| DTO | Type-safe, immutable data encapsulation | `app/Dto/[Resource]/` |
| Transformer | Format DTOs for API responses | `app/Transformers/` |
| Form Request | Validate & normalise input | `app/Http/Requests/[Resource]/` |
| CQRS Command Bus | Decouple write intent from execution | `app/CQRS/` |

### CQRS Command Bus

Used by `POST /products` and `POST /orders`. The controller builds an immutable **Command**, dispatches it via `CommandBus`, which resolves the registered **Handler**. The handler delegates to the service layer — no business logic in the handler.

**Adding a new command:**
1. Create `app/CQRS/Commands/<Domain>/MyCommand.php` (implements `CommandInterface`)
2. Create `app/CQRS/Handlers/<Domain>/MyCommandHandler.php` (implements `CommandHandlerInterface`)
3. Register in `AppServiceProvider`: `MyCommand::class => MyCommandHandler::class`
4. Dispatch from the relevant controller

---

## Request Flow

**Example: `GET /api/v1/products?filter[category_id]=1&sort=price&order=asc`**

```
Route dispatch → ListProductsController
  → ListProductsRequest validates & normalises params
  → ProductService::listProducts(page, perPage, sort, order, filters)
    → ProductRepository builds query (WHERE, ORDER BY, LIMIT)
    → Eloquent executes SQL, returns models
    → Repository maps models → Product DTOs
  → ProductTransformer converts DTOs → arrays
  → ListProductsResponse wraps in { success, data, meta, message }
→ HTTP 200 OK with JSON body
```

---

## RBAC & Middleware

### Roles

The `users` table has a `role` column (`enum: user | admin`, default: `user`).  
`UserModel::isAdmin()` returns `true` when `role === 'admin'`.

### Middleware

| Alias | Class | Behaviour |
|-------|-------|-----------|
| `auth.required` | `App\Http\Middleware\RequireAuth` | Returns `401` if the request has no authenticated user |
| `admin.required` | `App\Http\Middleware\RequireAdmin` | Returns `401` if unauthenticated, `403` if authenticated but not admin |

Both aliases are registered in `bootstrap/app.php`.

### Route Groups

```
routes/api.php
├── Public (no middleware)
│   ├── GET /products, GET /products/{id}
│   ├── GET /products/search
│   ├── GET /categories, GET /categories/{id}, GET /categories/{id}/subcategories
│   └── POST /webhooks/payments
│
├── auth.required
│   ├── POST   /orders          (place order — dispatches CreateOrderCommand via CommandBus)
│   ├── GET    /orders          (scoped to own orders for non-admins)
│   ├── GET    /orders/{id}     (own order only for non-admins)
│   ├── PUT    /orders/{id}     (restricted transitions for non-admins)
│   ├── POST   /coupons/apply   (validate and preview coupon discount)
│   ├── POST   /return-requests (create return request)
│   ├── GET    /return-requests (scoped to own for non-admins)
│   └── GET    /return-requests/{id} (own only for non-admins)
│
└── admin.required
    ├── POST   /products        (dispatches CreateProductCommand via CommandBus)
    ├── PUT    /products/{id}
    ├── DELETE /products/{id}
    ├── GET    /products/{id}/inventory-history
    ├── POST   /categories
    ├── PUT    /categories/{id}
    ├── DELETE /categories/{id}
    ├── DELETE /orders/{id}
    ├── GET    /coupons
    ├── GET    /coupons/{id}
    ├── POST   /coupons
    ├── PUT    /coupons/{id}
    ├── DELETE /coupons/{id}
    ├── POST   /return-requests/{id}/approve
    └── POST   /return-requests/{id}/reject
```

> **Note:** The CQRS command bus is used by `CreateProductController` and `CreateOrderController` — the `Admin*Controller` classes in `app/Http/Controllers/Api/V1/Admin/` are not currently registered in the routes.

---

## Order Placement & Inventory

### Concurrency model

Every read-decide-write path takes a row lock (`SELECT … FOR UPDATE`) and re-checks its precondition **inside the transaction**, so concurrent requests cannot both act on stale state:

| Path | Lock | What it prevents |
|------|------|------------------|
| `POST /orders` | product rows | Overselling |
| Cancel / delete order, webhooks (`paid`, `payment_failed`, `shipped`, `delivered`) | order row | Double stock restore; duplicate webhooks both passing the state machine |
| Approve / reject return request | return-request row (then order row) | Two admins both restoring stock |
| `PUT /products/{id}` | product row | Concurrent edits; see optimistic locking below |

**Deadlock avoidance:** product rows are always locked in ascending id order (`OrderService::adjustStock`), regardless of the order of items in the request.

**Restore-once guarantee:** `orders.stock_restored_at` is set the first time an order's stock is given back (cancel, delete or approved return). `restoreStock()` is a no-op afterwards, so no combination of those paths can restore twice.

**Optimistic locking (products):** `products.version` increases on every change (including stock movements from orders). `PUT /products/{id}` accepts the `version` the client last read and answers `409 Conflict` if it is stale, so an admin's edit cannot silently overwrite a sale. Omitting `version` skips the check.

**Idempotent order creation:** an optional `Idempotency-Key` header is stored in `orders.idempotency_key` (unique per user). A retry returns the original order instead of creating a second one; a concurrent retry that loses the race hits the unique index and also returns the original.

**Unique constraints as the source of truth:** category/product names, coupon codes and one-active-return-request-per-order are enforced by unique indexes. Services catch `UniqueConstraintViolationException` and raise the matching exception instead of pre-checking. A global handler in `bootstrap/app.php` turns any other unique violation into `409` without exposing SQL.

### Coupons

`POST /orders` accepts an optional `coupon_code`. Inside the order transaction the coupon is validated and one use is consumed with a single conditional `UPDATE … WHERE times_used < max_uses`, so `max_uses` cannot be exceeded under concurrency. The coupon id and discount are stored on the order (`coupon_id`, `discount_amount`). Cancelling or deleting the order releases the use.

### Inventory history

Every stock movement is recorded in `inventory_history`. The `change_type` column is backed by the `App\Enums\InventoryChangeType` enum for compile-time type safety:

| Event | `change_type` | `quantity_changed` |
|-------|---------------|--------------------|
| Product created | `addition` | `+initialQty` |
| Admin adjusts quantity | `adjustment` | `±delta` |
| Order placed | `sale` | `-orderedQty` |
| Order cancelled (paid/processing), deleted, or return approved | `return` | `+restoredQty` |

### Order deletion and inventory restoration

`OrderService::deleteOrder()` runs in a single transaction:

1. Locks the order row and loads its items
2. Restores stock (product rows locked in id order) and records `return` history entries — skipped if the stock was already restored (`stock_restored_at`), e.g. for a cancelled or refunded order
3. Releases the coupon use, unless cancellation already did
4. Deletes the order (`order_items` go via `ON DELETE CASCADE`)
5. Flushes the product cache

---

## Order Status Machine

`app/Services/Order/OrderStatusMachine.php` — a dedicated class that owns all transition rules, keeping them out of the controller and repository layers. This gives single responsibility, easy testability (unit-test in isolation), and replaceability (change rules in one file).

### Order lifecycle

```
                  ┌──────────┐
         ┌────────│  pending  │──────────────────────┐
         │        └──────────┘                        │
         │           │                                │
         │           │ (webhook) payment_failed       │ (user*/admin) cancelled
         │           ▼                                │
         │     ┌─────────────────┐                    │
         │     │ payment_failed  │────────────────────┤
         │     └─────────────────┘                    │
         │           │ (webhook) paid                 │
         │           │                                │
         │ (webhook/ ▼                                │
         │  admin)  ┌─────────┐                       │
         ├──────────│  paid   │───────────────────────┤ (user*/admin) cancelled
         │          └─────────┘                       │   → auto-refund
         │           │       │                        │
         │           │       │ (admin) refunded       │
         │           │       ▼                        │
         │           │   ┌──────────┐                 │
         │           │   │ refunded │ ◄─ terminal     │
         │           │   └──────────┘                 │
         │           │                                │
         │           │ (admin) processing             │
         │           ▼                                │
         │     ┌─────────────┐                        │
         │     │ processing  │────────────────────────┤ (admin) cancelled
         │     └─────────────┘                        │   → auto-refund
         │           │                                │
         │           │ (webhook/admin) shipped        │
         │           ▼                                ▼
         │     ┌──────────┐                  ┌─────────────┐
         │     │ shipped  │                  │  cancelled  │ ◄─ terminal
         │     └──────────┘                  └─────────────┘
         │           │
         │           │ (webhook/admin) delivered
         │           ▼
         │     ┌───────────┐
         │     │ delivered │
         │     └───────────┘
         │           │
         │           │ (admin/return approved) refunded
         │           ▼
         │     ┌──────────┐
         └────▸│ refunded │ ◄─ terminal
               └──────────┘
```

*User cancellation is only permitted within **24 hours** of order creation.

### Three rule sets, three methods

The machine exposes three methods for different actors:

#### `assertUserTransitionAllowed(Order $existing, OrderStatus $newStatus)`

Enforces what a **regular customer** may request:

| From | To | Extra condition |
|------|----|-----------------|
| `pending` | `cancelled` | Must be within 24 hours of `created_at` |
| `payment_failed` | `cancelled` | Must be within 24 hours of `created_at` |
| `paid` | `cancelled` | Must be within 24 hours of `created_at` → auto-refund + stock restored |

Any other transition throws `InvalidOrderStateException` → `400 Bad Request`. Once an order reaches `processing`, only admins can cancel it.

#### `assertAdminTransitionAllowed(Order $existing, OrderStatus $newStatus)`

Enforces the **complete lifecycle** for operators:

| From | To |
|------|----|
| `pending` | `paid`, `cancelled` |
| `payment_failed` | `paid`, `cancelled` |
| `paid` | `processing`, `refunded`, `cancelled` |
| `processing` | `shipped`, `cancelled` |
| `shipped` | `delivered` |
| `delivered` | `refunded` |
| `cancelled` | *(terminal — no outgoing transitions)* |
| `refunded` | *(terminal — no outgoing transitions)* |

Cancelling a `paid` or `processing` order triggers auto-refund with stock restoration.

#### `assertWebhookTransitionAllowed(Order $existing, OrderStatus $newStatus)`

Enforces what **external systems** (payment providers, shipping carriers) can trigger via webhooks:

| From | To | Webhook |
|------|----|---------|
| `pending` | `paid` | Payment (success) |
| `pending` | `payment_failed` | Payment (failure) |
| `payment_failed` | `paid` | Payment (retry success) |
| `processing` | `shipped` | Shipping (carrier picked up) |
| `shipped` | `delivered` | Shipping (carrier delivered) |

> **Note:** Webhooks cannot skip steps — a shipping carrier cannot mark an order as `shipped` unless it's in `processing` (admin must start fulfilment first).

All three methods throw `InvalidOrderStateException` on violation and return `void` on success.

The machine also owns two eligibility rules that are not transitions:

- `isRefundable(Order)` — `paid` and `processing` orders are refunded (stock restored) when cancelled.
- `assertReturnAllowed(Order)` — return requests may be created **and approved** only for `paid`, `shipped` or `delivered` orders. Approval re-checks this under lock, so a return cannot be approved after its order was cancelled.

### Cancellation with auto-refund

When an order in `paid` or `processing` status is cancelled (by user or admin), `OrderService` automatically (all inside one transaction, with the order row locked and the transition re-validated under that lock):

1. Restores stock for each order item (product rows locked in id order; no-op if already restored)
2. Records `Return` entries in `inventory_history`
3. Updates the order status to `cancelled`
4. Invalidates product caches
5. Dispatches `OrderCancelledEvent` with `refundIssued: true`
6. Sends a cancellation email to the customer (with refund notice)

Cancelling a `pending` or `payment_failed` order skips the refund (no payment was made).

### Integration

```
PUT /api/v1/orders/{id}
UpdateOrderController::executeRequest()
  ├── 1. Auth / ownership check
  ├── 2. Resolve isAdmin = auth()->user()->isAdmin()
  └── OrderService::updateOrder(id, unpersisted, asAdmin: isAdmin)
        └── DB transaction
              ├── 3. OrderRepository::findByIdForUpdate(id)   (row lock)
              ├── 4a. if asAdmin  → statusMachine::assertAdminTransitionAllowed(existing, newStatus)
              │   4b. if !asAdmin → statusMachine::assertUserTransitionAllowed(existing, newStatus)
              │         └── also checks 24-hour cancellation window
              ├── 5a. if cancelled → cancelOrder() (auto-refund if statusMachine::isRefundable)
              └── 5b. otherwise   → OrderRepository::update(id, unpersisted)

POST /api/v1/webhooks/payments
PaymentWebhookController::__invoke()
  └── match status:
        ├── "paid"           → OrderService::markOrderAsPaid(orderId, paymentRef)
        └── "payment_failed" → OrderService::markOrderAsPaymentFailed(orderId, paymentRef)

POST /api/v1/webhooks/shipping
ShippingWebhookController::__invoke()
  └── match event:
        ├── "shipped"   → OrderService::markOrderAsShipped(orderId, trackingNumber)
        └── "delivered"  → OrderService::markOrderAsDelivered(orderId)
```

### Adding a new transition

1. Add the new `OrderStatus` case to `app/Enums/OrderStatus.php` if needed.
2. Add the transition to the appropriate constant (`ADMIN_ALLOWED_TRANSITIONS`, `USER_ALLOWED_TRANSITIONS`, or `WEBHOOK_ALLOWED_TRANSITIONS`).
3. Add a migration if the `status` column type needs updating.
4. Add a test case to `tests/Unit/Services/OrderStatusMachineTest.php`.

No other file needs to change.

---

## Caching

Tagged caching via Laravel's `Cache` facade. Invalidation is performed in the service layer after every successful write.

| Resource | Tag | TTL | Cached methods |
|----------|-----|-----|----------------|
| Categories | `categories` | 30 min | `getAll`, `findById`, `getSubcategoriesById` |
| Products | `products` | 5 min | `getAll`, `findById`, `findByCategoryId` |
| Orders | — | — | Not cached (user-specific, real-time state) |
| Inventory history | — | — | Not cached (append-only admin log) |

**Configuration:** `CACHE_STORE=array` (dev, in-memory per request) or `CACHE_STORE=redis` (production, cross-request).

**Key strategy:** List endpoints use `md5(serialize($params))` for deterministic keys per unique query.

**Invalidation:** On any write the entire tag group is flushed (`Cache::tags()->flush()`) — everything cached under a tag lives in that group, so per-key `Cache::forget` is neither needed nor effective for tagged entries. Cache is only flushed on success — failed mutations leave the cache intact. Anything that changes stock (orders, cancellations, deletes, approved returns) also flushes the `products` tag. A concurrent reader can briefly re-cache pre-commit data; it expires with the TTL and never affects stock correctness (writes read under lock).

---

## Audit Logging

All mutations are recorded to a dedicated `audit` channel (`app/Services/AuditLogger.php`) after every successful write — failed operations are not logged.

| Service | Actions |
|---------|---------|
| ProductService | `product.created`, `product.updated`, `product.deleted` |
| CategoryService | `category.created`, `category.updated`, `category.deleted` |
| OrderService | `order.created`, `order.paid`, `order.payment_failed`, `order.shipped`, `order.delivered`, `order.updated`, `order.cancelled`, `order.deleted` |
| CouponService | `coupon.created`, `coupon.updated`, `coupon.deleted` |
| ReturnRequestService | `return_request.created`, `return_request.approved`, `return_request.rejected` |

Each entry contains: `action`, `entity`, `entity_id`, `user_id`, `properties`, `ip`, `timestamp`.

**Channel:** Daily log file at `storage/logs/audit.log` with 90-day retention, configured in `config/logging.php`.

---

## Webhooks

### Inbound → Outbound flow

```
External payment provider
  │  POST /api/v1/webhooks/payments (HMAC-SHA256 verified)
  ▼
PaymentWebhookController → OrderService::markOrderAsPaid()
  → OrderPaidEvent::dispatch()
    → SendOrderPaidWebhook (queued listener)
      → HTTP POST to WEBHOOK_ORDER_PAID_URL
```

**Inbound:** `POST /webhooks/payments` receives payment confirmations (`paid` / `payment_failed`). `POST /webhooks/shipping` receives carrier events (`shipped` / `delivered`). Both validate via HMAC-SHA256 when `WEBHOOK_SIGNING_SECRET` is set.

**Outbound:** On `order.paid`, a queued listener POSTs to `WEBHOOK_ORDER_PAID_URL` (disabled when unset). Payload includes event type, order details, and items.

| Property | Value |
|----------|-------|
| Queue | `webhooks` |
| Retries | 3 × 10s backoff |
| Timeout | 10s per request |

---

## Email Notifications

Queued email notifications sent at key order lifecycle events:

| Trigger | Event → Listener | Mailable |
|---------|-------------------|----------|
| Order placed | `OrderCreatedEvent` → `SendOrderConfirmationEmail` | `OrderConfirmationMail` |
| Payment confirmed | `OrderPaidEvent` → `SendOrderPaidEmail` | `OrderPaidMail` |
| Order shipped | `OrderShippedEvent` → `SendOrderShippedEmail` | `OrderShippedMail` |

All listeners implement `ShouldQueue` and run on the `emails` queue (3 retries, 30s backoff). Blade templates live in `resources/views/emails/`. Default mailer is `log` (dev) — configure `MAIL_MAILER=smtp` for production.

---

## Error Handling

Custom exceptions map to HTTP status codes via `App/Exceptions/Handler.php`:

| Exception | HTTP Status |
|-----------|-------------|
| `ProductNotFoundException`, `CategoryNotFoundException`, `OrderNotFoundException`, `CouponNotFoundException`, `ReturnRequestNotFoundException` | `404` |
| `ProductAlreadyExistsException`, `CategoryAlreadyExistsException`, `ProductVersionConflictException`, any unmapped `UniqueConstraintViolationException` | `409` |
| `InsufficientStockException`, `InvalidOrderStateException`, `InvalidCouponException`, `InvalidReturnRequestStateException`, `BadRequestException` | `400` |
| `UnprocessableEntityException`, `ValidationException` | `422` |

All error responses follow the standard format: `{ "success": false, "message": "..." }`.
