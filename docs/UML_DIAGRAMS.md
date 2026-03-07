# Shop API - UML Diagrams

## Table of Contents
1. [Entity Relationship Diagram](#entity-relationship-diagram)
2. [Component Diagram](#component-diagram)
3. [Deployment Diagram](#deployment-diagram)
4. [Order State Diagram](#order-state-diagram)
5. [Order Lifecycle Sequence Diagram](#order-lifecycle-sequence-diagram)

---

## Entity Relationship Diagram

```mermaid
classDiagram
    class User {
        +int id
        +string name
        +string email
        +string password
        +string role
        +string address_line1
        +string city
        +string state
        +string zip_code
        +string country
        +string phone_number
        +datetime created_at
    }

    class Category {
        +int id
        +int parent_id
        +string name
        +string description
        +datetime created_at
        +datetime updated_at
    }

    class Product {
        +int id
        +int category_id
        +string name
        +string description
        +float price
        +int quantity
        +datetime created_at
        +datetime updated_at
    }

    class Order {
        +int id
        +int user_id
        +int coupon_id
        +OrderStatus status
        +float total_price
        +float discount_amount
        +datetime created_at
        +datetime updated_at
    }

    class OrderItem {
        +int id
        +int order_id
        +int product_id
        +int quantity
        +float unit_price
        +datetime created_at
        +datetime updated_at
    }

    class Coupon {
        +int id
        +string code
        +CouponType type
        +float value
        +float min_order_amount
        +int max_uses
        +int times_used
        +datetime expires_at
        +bool is_active
        +datetime created_at
        +datetime updated_at
    }

    class ReturnRequest {
        +int id
        +int order_id
        +int user_id
        +string reason
        +ReturnRequestStatus status
        +string admin_notes
        +datetime created_at
        +datetime updated_at
    }

    class InventoryHistory {
        +int id
        +int product_id
        +InventoryChangeType change_type
        +int quantity_changed
        +int previous_quantity
        +int new_quantity
        +datetime created_at
        +datetime updated_at
    }

    User "1" --> "0..*" Order : places
    User "1" --> "0..*" ReturnRequest : submits
    Order "1" --> "1..*" OrderItem : contains
    Order "0..*" --> "0..1" Coupon : applies
    Order "1" --> "0..*" ReturnRequest : has
    OrderItem "0..*" --> "1" Product : references
    Product "1" --> "0..*" InventoryHistory : tracks
    Product "0..*" --> "1" Category : belongs to
    Category "0..1" --> "0..*" Category : parent/children
```

---

## Component Diagram

```mermaid
graph TB
    subgraph "HTTP Layer"
        nginx["Nginx<br/>(Reverse Proxy)"]
        middleware["Middleware<br/>RequireAuth / RequireAdmin"]
        controllers["Controllers<br/>(Api/V1)"]
        requests["Form Requests<br/>(Validation)"]
        responses["Response Objects"]
    end

    subgraph "Application Layer"
        commandBus["CommandBus<br/>(CQRS)"]
        commands["Commands<br/>(Order, Product)"]
        handlers["Command Handlers"]
        services["Services<br/>(Business Logic)"]
        statusMachine["OrderStatusMachine<br/>(State Transitions)"]
        transformers["Transformers<br/>(Response Mapping)"]
        dtos["DTOs<br/>(Data Transfer Objects)"]
    end

    subgraph "Domain Layer"
        models["Eloquent Models"]
        enums["Enums<br/>(OrderStatus, CouponType, ...)"]
        events["Domain Events<br/>(OrderCreated, OrderPaid, OrderShipped)"]
        exceptions["Domain Exceptions"]
    end

    subgraph "Infrastructure Layer"
        repositories["Repositories<br/>(Data Access)"]
        listeners["Event Listeners"]
        mail["Mailables<br/>(Email Templates)"]
        auditLogger["AuditLogger"]
    end

    subgraph "External"
        postgres[("PostgreSQL 16")]
        redis[("Redis 7")]
        queueWorker["Queue Worker"]
        mailServer["Mail Server"]
        webhookEndpoint["External Webhook<br/>(Payment Provider)"]
    end

    nginx --> middleware
    middleware --> controllers
    controllers --> requests
    controllers --> commandBus
    controllers --> services
    controllers --> transformers
    commandBus --> commands
    commandBus --> handlers
    handlers --> services
    services --> statusMachine
    services --> repositories
    services --> dtos
    services --> events
    services --> auditLogger
    transformers --> dtos
    repositories --> models
    models --> enums
    events --> listeners
    listeners --> mail
    listeners --> queueWorker
    queueWorker --> mailServer
    queueWorker --> webhookEndpoint
    repositories --> postgres
    services --> redis
    controllers --> responses
```

---

## Deployment Diagram

```mermaid
graph TB
    subgraph "Docker Network: shop-network"
        subgraph "nginx container"
            nginxService["Nginx :80<br/>nginx:alpine<br/><i>Reverse proxy + static files</i>"]
        end

        subgraph "app container"
            phpFpm["PHP-FPM :9000<br/>Laravel Application<br/><i>API request handling</i>"]
        end

        subgraph "queue container"
            queueWorker["Queue Worker<br/>php artisan queue:work<br/><i>Async job processing<br/>(emails, webhooks)</i>"]
        end

        subgraph "postgres container"
            postgresDb[("PostgreSQL 16 :5432<br/><i>Primary datastore</i><br/>Volume: postgres_data")]
        end

        subgraph "redis container"
            redisCache[("Redis 7 :6379<br/><i>Cache + Queue broker</i><br/>128MB / allkeys-lru")]
        end
    end

    client["Client<br/>(Browser / API Consumer)"]
    paymentProvider["Payment Provider<br/>(External Webhook)"]

    client -- "HTTP :8081" --> nginxService
    paymentProvider -- "POST /api/v1/webhooks/payments" --> nginxService
    nginxService -- "FastCGI :9000" --> phpFpm
    phpFpm -- "SQL" --> postgresDb
    phpFpm -- "Cache / Queue dispatch" --> redisCache
    queueWorker -- "Poll jobs" --> redisCache
    queueWorker -- "SQL" --> postgresDb
    phpFpm -. "health: service_healthy" .-> postgresDb
    phpFpm -. "health: service_healthy" .-> redisCache
    nginxService -. "depends_on: app healthy" .-> phpFpm
    queueWorker -. "depends_on: app healthy" .-> phpFpm
```

### Production Overrides (`docker-compose.prod.yml`)

| Service  | CPU Limit | Memory Limit | Security                                    |
|----------|-----------|--------------|---------------------------------------------|
| app      | 1.0       | 512 MB       | read-only, no-new-privileges, cap-drop: ALL |
| queue    | 0.5       | 256 MB       | read-only, no-new-privileges, cap-drop: ALL |
| nginx    | 0.5       | 128 MB       | read-only, cap-add: NET_BIND_SERVICE only   |
| postgres | 2.0       | 1 GB         | Internal port only (no host binding)        |
| redis    | 0.5       | 384 MB       | requirepass, internal port only              |

---

## Order State Diagram

```mermaid
stateDiagram-v2
    [*] --> Pending : Order created

    Pending --> Cancelled : User cancels\n(within 24h window)
    Pending --> Paid : Payment webhook\nor Admin
    Pending --> Cancelled : Admin cancels

    Paid --> Shipped : Admin ships
    Paid --> Refunded : Admin refunds

    Shipped --> Delivered : Admin confirms delivery

    Delivered --> Refunded : Admin refunds

    Cancelled --> [*]
    Refunded --> [*]

    note right of Pending
        Initial state.
        Users can only cancel (within 24h).
        Admins can mark as paid or cancel.
    end note

    note right of Cancelled
        Terminal state.
        No outgoing transitions.
    end note

    note right of Refunded
        Terminal state.
        No outgoing transitions.
    end note
```

### Transition Rules

| Actor | From | To | Condition |
|-------|------|----|-----------|
| User  | `pending` | `cancelled` | Within 24 hours of `created_at` |
| Admin | `pending` | `paid` | — |
| Admin | `pending` | `cancelled` | — |
| Webhook | `pending` | `paid` | Valid payment reference |
| Admin | `paid` | `shipped` | — |
| Admin | `paid` | `refunded` | — |
| Admin | `shipped` | `delivered` | — |
| Admin | `delivered` | `refunded` | — |

> `cancelled` and `refunded` are **terminal states** — no outgoing transitions are allowed.

---

## Order Lifecycle Sequence Diagram

```mermaid
sequenceDiagram
    actor Customer
    participant Nginx
    participant Controller as CreateOrderController
    participant CommandBus
    participant Handler as CreateOrderHandler
    participant OrderService
    participant ProductRepo as ProductRepository
    participant OrderRepo as OrderRepository
    participant InventoryRepo as InventoryHistoryRepository
    participant DB as PostgreSQL
    participant Redis
    participant EventDispatcher as Event Dispatcher
    participant Queue as Queue Worker
    participant MailServer as Mail Server

    Note over Customer,MailServer: 1. Order Creation

    Customer->>Nginx: POST /api/v1/orders
    Nginx->>Controller: FastCGI forward
    Controller->>Controller: Validate request (CreateOrderRequest)
    Controller->>CommandBus: dispatch(CreateOrderCommand)
    CommandBus->>Handler: handle(command)
    Handler->>OrderService: createOrder(unpersistedOrder)

    rect rgb(255, 245, 230)
        Note over OrderService,DB: DB Transaction (pessimistic locking)
        loop For each order item
            OrderService->>ProductRepo: findByIdForUpdate(productId)
            ProductRepo->>DB: SELECT ... FOR UPDATE
            DB-->>ProductRepo: product (locked row)
            ProductRepo-->>OrderService: product
            OrderService->>OrderService: Validate stock availability
            OrderService->>ProductRepo: update quantity
            ProductRepo->>DB: UPDATE products SET quantity = ...
            OrderService->>InventoryRepo: record(Sale entry)
            InventoryRepo->>DB: INSERT INTO inventory_history
        end
        OrderService->>OrderRepo: persist(order + items)
        OrderRepo->>DB: INSERT INTO orders + order_items
        DB-->>OrderService: order (committed)
    end

    OrderService->>Redis: Invalidate product caches
    OrderService->>EventDispatcher: dispatch(OrderCreatedEvent)
    EventDispatcher->>Queue: Push SendOrderConfirmationEmail job

    OrderService-->>Controller: order DTO
    Controller-->>Nginx: 201 Created (JSON)
    Nginx-->>Customer: Response

    Queue->>MailServer: Send confirmation email (async)

    Note over Customer,MailServer: 2. Payment Processing

    participant PaymentProvider as Payment Provider
    participant WebhookCtrl as PaymentWebhookController

    PaymentProvider->>Nginx: POST /api/v1/webhooks/payments
    Nginx->>WebhookCtrl: FastCGI forward
    WebhookCtrl->>WebhookCtrl: Validate (PaymentWebhookRequest)
    WebhookCtrl->>OrderService: markOrderAsPaid(orderId, paymentRef)
    OrderService->>OrderRepo: findById(orderId)
    OrderService->>OrderService: Assert status == pending
    OrderService->>OrderRepo: update(status: paid)
    OrderRepo->>DB: UPDATE orders SET status = 'paid'
    OrderService->>EventDispatcher: dispatch(OrderPaidEvent)
    EventDispatcher->>Queue: Push SendOrderPaidEmail job
    EventDispatcher->>Queue: Push SendOrderPaidWebhook job
    OrderService-->>WebhookCtrl: updated order
    WebhookCtrl-->>Nginx: 200 OK
    Nginx-->>PaymentProvider: Response

    Queue->>MailServer: Send payment confirmation email (async)

    Note over Customer,MailServer: 3. Shipping & Delivery

    actor Admin

    Admin->>Nginx: PUT /api/v1/orders/{id} {status: "shipped"}
    Nginx->>Controller: FastCGI forward
    Controller->>OrderService: updateOrder(id, status: shipped, asAdmin: true)
    OrderService->>OrderService: StatusMachine.assertAdminTransitionAllowed(paid → shipped)
    OrderService->>OrderRepo: update(status: shipped)
    OrderRepo->>DB: UPDATE orders SET status = 'shipped'
    OrderService->>EventDispatcher: dispatch(OrderShippedEvent)
    EventDispatcher->>Queue: Push SendOrderShippedEmail job
    OrderService-->>Controller: updated order
    Controller-->>Nginx: 200 OK
    Nginx-->>Admin: Response

    Queue->>MailServer: Send shipping notification email (async)
```

