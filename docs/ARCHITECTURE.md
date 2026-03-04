# Shop API - Architecture Guide

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Design Patterns](#design-patterns)
3. [Request Flow](#request-flow)
4. [Layer Responsibilities](#layer-responsibilities)
5. [Data Flow](#data-flow)
6. [RBAC & Middleware](#rbac--middleware)
7. [Order Placement & Inventory](#order-placement--inventory)
8. [Order Status Machine](#order-status-machine)
9. [Caching](#caching)
10. [Audit Logging](#audit-logging)
11. [Error Handling](#error-handling)
12. [Extension Points](#extension-points)

---

## Architecture Overview

The Shop API follows a **layered hexagonal architecture** with clear separation of concerns:

```
┌─────────────────────────────────────────────────┐
│              HTTP Request                       │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│           Route Dispatch                        │
│        (routes/api.php)                         │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│            Controller Layer                     │
│   (Api/V1/[Resource]/[Action]Controller)       │
│  - Receive HTTP request                         │
│  - Call service method                          │
│  - Return HTTP response                         │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│      Request Validation Layer                  │
│   (Http/Requests/[Resource]/...)              │
│  - Validate input                               │
│  - Normalize data                               │
│  - Cast types                                   │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│         Service Layer                           │
│    (Services/[Resource]/[Resource]Service)      │
│  - Business logic                               │
│  - Orchestrate operations                       │
│  - Handle complex workflows                     │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│         Repository Layer                        │
│  (Repositories/[Resource]/[Resource]Repository) │
│  - Data access abstraction                      │
│  - Query building                               │
│  - Database operations                          │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│            Model Layer                          │
│      (Models/[Resource]/[Resource]Model)        │
│  - Database representation                      │
│  - Eloquent ORM                                 │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│            Database                             │
│         (SQLite)                                │
└─────────────────────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│       Response Transformation                   │
│    (Transformers/[Resource]Transformer)         │
│  - Format output                                │
│  - DTO to array                                 │
│  - Include relationships                        │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│      Response Objects                           │
│   (Http/Responses/[Resource]/...)              │
│  - Standardize response format                  │
│  - Add metadata                                 │
│  - Add messages                                 │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│           HTTP Response                         │
└─────────────────────────────────────────────────┘
```

---

## Design Patterns

### 1. **Repository Pattern**

**Purpose:** Abstract data access logic from business logic

**Location:** `app/Repositories/[Resource]/[Resource]Repository.php`

**Responsibilities:**
- Build database queries
- Execute queries
- Return data objects

**Example:**
```php
class ProductRepository
{
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
        array $includes = []
    ): LengthAwarePaginator {
        $query = ProductModel::query();
        
        // Apply includes
        if (!empty($includes)) {
            $query->with($includes);
        }
        
        // Apply filters
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        
        // Apply sorting
        $query->orderBy($sort, $order);
        
        // Paginate and transform
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->getCollection()->map(fn($model) => Product::fromModel($model));
        $paginator->setCollection($items);
        
        return $paginator;
    }
}
```

**Benefits:**
- Easy to test (mock repository)
- Database-agnostic business logic
- Query logic centralized
- Reusable queries

---

### 2. **Service Layer Pattern**

**Purpose:** Encapsulate business logic

**Location:** `app/Services/[Resource]/[Resource]Service.php`

**Responsibilities:**
- Coordinate operations
- Apply business rules
- Validate data
- Use repositories

**Example:**
```php
class ProductService
{
    public function __construct(
        private ProductRepository $repository,
        private InventoryHistoryRepository $inventoryHistoryRepository,
    ) {}

    public function createProduct(UnpersistedProduct $unpersistedProduct): Product
    {
        return DB::transaction(function () use ($unpersistedProduct) {
            $existing = $this->repository->findByName($unpersistedProduct->name);

            if ($existing) {
                throw new ProductAlreadyExistsException($unpersistedProduct->name);
            }

            $created = $this->repository->persist($unpersistedProduct);

            // Record initial stock addition atomically with the insert.
            $this->inventoryHistoryRepository->record(new UnpersistedInventoryHistoryEntry(
                productId: $created->id,
                changeType: 'addition',
                quantityChanged: $created->quantity,
                previousQuantity: 0,
                newQuantity: $created->quantity,
            ));

            return $created;
        });
    }

    public function updateProduct(int $id, UnpersistedProduct $unpersistedProduct): Product
    {
        return DB::transaction(function () use ($id, $unpersistedProduct) {
            // Acquire a pessimistic write lock to prevent concurrent
            // updates from causing stock inconsistencies.
            $lockedModel = $this->repository->findByIdForUpdate($id);

            if ($lockedModel === null) {
                throw new ProductNotFoundException($id);
            }

            if ($unpersistedProduct->quantity < 0) {
                throw new InsufficientStockException($id, $unpersistedProduct->quantity, $lockedModel->quantity);
            }

            $updated = $this->repository->update($id, $unpersistedProduct);

            if ($updated->quantity !== $lockedModel->quantity) {
                $this->inventoryHistoryRepository->record(/* ... */);
            }

            return $updated;
        });
    }
}
```

**Benefits:**
- Clear business logic
- Easy to test (mock dependencies)
- Reusable across controllers
- Consistent behavior

---

### 3. **Data Transfer Object (DTO) Pattern**

**Purpose:** Type-safe data encapsulation

**Location:** `app/Dto/[Resource]/[Resource].php`

**Responsibilities:**
- Encapsulate data
- Provide type safety
- Define properties

**Example:**
```php
final readonly class Product
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public float $price,
        public int $quantity,
        public int $category_id,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ProductModel $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            price: $model->price,
            quantity: $model->quantity,
            category_id: $model->category_id,
            created_at: $model->created_at->toIso8601String(),
            updated_at: $model->updated_at->toIso8601String(),
        );
    }
}
```

**Benefits:**
- Type safety (read-only properties)
- IDE autocompletion
- Data validation
- Easy to extend

---

### 4. **Transformer/Presenter Pattern**

**Purpose:** Format DTOs for API responses

**Location:** `app/Transformers/[Resource]Transformer.php`

**Responsibilities:**
- Convert DTO to array
- Include relationships
- Format data

**Example:**
```php
class ProductTransformer
{
    public function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'category_id' => $product->category_id,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}
```

**Benefits:**
- Consistent output format
- Easy to customize responses
- Separation from business logic
- Relationship inclusion

---

### 6. **CQRS Command Bus Pattern**

**Purpose:** Decouple write *intent* from its execution inside the existing endpoints

**Location:** `app/CQRS/`

```
app/CQRS/
├── CommandBus.php                          # Resolves and dispatches to handler
├── Commands/
│   ├── CommandInterface.php               # Marker interface for all commands
│   ├── Product/
│   │   └── CreateProductCommand.php       # Immutable value object
│   └── Order/
│       ├── CreateOrderCommand.php
│       └── CreateOrderCommandItem.php
└── Handlers/
    ├── CommandHandlerInterface.php        # Contract for all handlers
    ├── Product/
    │   └── CreateProductCommandHandler.php
    └── Order/
        └── CreateOrderCommandHandler.php
```

**How it works — using the existing endpoints:**

```
POST /api/v1/products          (existing, admin.required middleware)
POST /api/v1/orders            (existing, auth.required middleware)
```

1. A request hits the existing `CreateProductController` / `CreateOrderController` — **no new routes**.
2. The controller constructs an immutable **Command** value object from the validated request.
3. It calls `CommandBus::dispatch($command)`.
4. The bus resolves the registered **Handler** from the container and calls `handle($command)`.
5. The handler delegates to the **Service** layer — no business logic lives in the handler itself.

**Service interfaces:** `ProductServiceInterface` and `OrderServiceInterface` are bound in `AppServiceProvider` so the container can inject them into handlers (and so tests can mock them without fighting `final`).

**Adding a new command:**

1. Add `app/CQRS/Commands/<Domain>/MyCommand.php` (implements `CommandInterface`)
2. Add `app/CQRS/Handlers/<Domain>/MyCommandHandler.php` (implements `CommandHandlerInterface`)
3. Register in `AppServiceProvider`: `MyCommand::class => MyCommandHandler::class`
4. Inject `CommandBus` into the relevant **existing** controller and dispatch

---

### 5. **Form Request Pattern**

**Purpose:** Decouple admin write intentions from their execution

**Location:** `app/CQRS/`

```
app/CQRS/
├── CommandBus.php                          # Resolves and dispatches to handler
├── Commands/
│   ├── CommandInterface.php               # Marker interface for all commands
│   ├── Product/
│   │   └── CreateProductCommand.php       # Immutable value object
│   └── Order/
│       ├── CreateOrderCommand.php
│       └── CreateOrderCommandItem.php
└── Handlers/
    ├── CommandHandlerInterface.php        # Contract for all handlers
    ├── Product/
    │   └── CreateProductCommandHandler.php
    └── Order/
        └── CreateOrderCommandHandler.php
```

**How it works:**

1. An admin HTTP request hits an `Admin*Controller` under `Api/V1/Admin/`.
2. The controller constructs an immutable **Command** value object from the validated request.
3. The controller calls `CommandBus::dispatch($command)`.
4. The bus resolves the registered **Handler** from the Laravel container and calls `handle($command)`.
5. The handler delegates to the existing **Service** layer — no business logic lives in the handler itself.

**Admin routes** (all under `admin.required` middleware):

| Method | URI | Name |
|--------|-----|------|
| `POST` | `/api/v1/admin/products` | `v1.admin.products.store` |
| `POST` | `/api/v1/admin/orders` | `v1.admin.orders.store` |

**Service interfaces:** `ProductServiceInterface` and `OrderServiceInterface` are bound in `AppServiceProvider` so the container can inject them into handlers. The concrete `ProductService` / `OrderService` classes implement these interfaces — no changes to existing service behaviour.

**Benefits:**
- Admin intent is explicit and auditable (each command is a named, typed object)
- Handlers are thin bridges — business logic stays in services
- Easy to extend: add a command + handler + route, nothing else changes
- Fully testable: handlers mock the service interface; feature tests hit the full stack

---

### 5. **Form Request Pattern**

**Purpose:** Validate and normalize input

**Location:** `app/Http/Requests/[Resource]/...Request.php`

**Responsibilities:**
- Validate input
- Cast types
- Set defaults

**Example:**
```php
class ListProductsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['id', 'name', 'price', 'quantity', 'created_at', 'updated_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'filter' => ['sometimes', 'array'],
            'filter.name' => ['sometimes', 'string'],
            'filter.category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'filter.min_price' => ['sometimes', 'numeric', 'min:0'],
            'filter.max_price' => ['sometimes', 'numeric', 'min:0'],
            'include' => ['sometimes', 'string'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();
        
        // Set defaults
        $validated['page'] = $validated['page'] ?? 1;
        $validated['per_page'] = $validated['per_page'] ?? 15;
        $validated['sort'] = $validated['sort'] ?? 'id';
        $validated['order'] = $validated['order'] ?? 'asc';
        $validated['filter'] = $validated['filter'] ?? [];
        $validated['include'] = isset($validated['include']) ? explode(',', $validated['include']) : [];
        
        return $validated;
    }
}
```

**Benefits:**
- Input validation in one place
- Type casting
- Default values
- Prevents invalid data reaching business logic

---

## Request Flow

### Complete Request Flow Example: List Products

```
1. HTTP Request
   GET /api/v1/products?filter[category_id]=1&sort=price&order=asc
   
   ↓
   
2. Route Dispatch
   routes/api.php matches route and dispatches to controller
   
   ↓
   
3. Controller Method
   ListProductsController::index(ListProductsRequest $request)
   
   ↓
   
4. Request Validation (Automatic)
   ListProductsRequest validates parameters
   Throws ValidationException if invalid
   
   ↓
   
5. Request Extraction
   Controller calls $request->validated()
   Gets validated and normalized data:
   {
     'page': 1,
     'per_page': 15,
     'sort': 'price',
     'order': 'asc',
     'filter': {'category_id': 1},
     'include': []
   }
   
   ↓
   
6. Service Call
   Controller calls service method
   $service->listProducts(page: 1, perPage: 15, sort: 'price', order: 'asc', filters: {...})
   
   ↓
   
7. Repository Call
   Service delegates to repository
   Repository builds query:
   ProductModel::query()
     ->with([])
     ->where('category_id', 1)
     ->orderBy('price', 'asc')
     ->paginate(15, ['*'], 'page', 1)
   
   ↓
   
8. Database Query
   SELECT * FROM products
   WHERE category_id = 1
   ORDER BY price ASC
   LIMIT 15 OFFSET 0
   
   ↓
   
9. Model Transformation
   Repository transforms models to DTOs:
   ProductModel → Product DTO
   
   ↓
   
10. Response Transformation
    Controller transforms DTOs to arrays:
    Product DTO → [id, name, price, ...]
    
    ↓
    
11. Response Object
    Controller wraps in response object:
    new ListProductsResponse($products, $metadata)
    
    ↓
    
12. JSON Serialization
    Response object converts to JSON:
    {
      "success": true,
      "data": [...],
      "meta": {...},
      "message": "Products found"
    }
    
    ↓
    
13. HTTP Response
    Return 200 OK with JSON body
```

---

## Layer Responsibilities

### Controller Layer
```
Responsibilities:
- Receive HTTP request
- Call appropriate service method
- Handle responses
- Return HTTP response

What it should NOT do:
- Database queries
- Business logic
- Data transformation (except controller-specific formatting)
```

### Request Layer
```
Responsibilities:
- Validate input data
- Cast types
- Set defaults
- Normalize data

What it should NOT do:
- Business logic
- Database queries
- Response formatting
```

### Service Layer
```
Responsibilities:
- Implement business logic
- Orchestrate operations
- Use repositories for data access
- Apply business rules
- Handle exceptions

What it should NOT do:
- Database queries (use repositories)
- HTTP logic
- Response formatting
```

### Repository Layer
```
Responsibilities:
- Abstract data access
- Build queries
- Execute database operations
- Transform models to DTOs

What it should NOT do:
- Business logic
- HTTP logic
- Response formatting
- Validation
```

### Model Layer
```
Responsibilities:
- Database representation
- Eloquent relationships
- Database migrations
- Attribute casting

What it should NOT do:
- Business logic
- API-specific logic
- Response formatting
```

### Transformer Layer
```
Responsibilities:
- Format DTOs to arrays
- Include relationships
- Customize output
- Add computed fields

What it should NOT do:
- Business logic
- Database queries
- Validation
```

---

## Data Flow

### Create Operation Flow

```
POST /api/v1/products
{
  "name": "New Product",
  "price": 99.99,
  "category_id": 1
}

↓ CreateProductRequest (validates)

↓ CreateProductController::store()

↓ ProductService::createProduct(UnpersistedProduct)
  - Opens DB transaction
  - Checks for duplicate name (throws ProductAlreadyExistsException)
  - Calls repository to persist product
  - Records initial stock addition in inventory_history
  - Commits transaction (or rolls back on failure)

↓ ProductRepository::persist(UnpersistedProduct)
  - Creates ProductModel
  - Saves to database
  - Returns Product DTO

↓ ProductTransformer::transform(Product)
  - Converts DTO to array

↓ CreateProductResponse
  - Wraps in response format
  - Adds success flag
  - Adds message

↓ HTTP 201 Created
{
  "success": true,
  "data": {
    "id": 1,
    "name": "New Product",
    ...
  },
  "message": "Product created successfully"
}
```

### Read Operation Flow

```
GET /api/v1/products/1

↓ GetProductController::show()

↓ ProductService::getProductById(1)

↓ ProductRepository::findById(1)
  - Query database
  - Transform model to DTO

↓ ProductTransformer::transform(Product)

↓ GetProductResponse

↓ HTTP 200 OK
{
  "success": true,
  "data": { ... }
}
```

### Update Operation Flow

```
PUT /api/v1/products/1
{
  "name": "Updated Name",
  "price": 149.99,
  "quantity": 20
}

↓ UpdateProductRequest (validates)

↓ UpdateProductController::update()

↓ ProductService::updateProduct(1, UnpersistedProduct)
  - Opens DB transaction
  - Calls repository::findByIdForUpdate(1) — acquires FOR UPDATE row lock
  - Validates new quantity >= 0 (throws InsufficientStockException otherwise)
  - Calls repository::update(1, UnpersistedProduct)
  - If quantity changed, records adjustment in inventory_history
  - Commits transaction (or rolls back on failure)

↓ ProductRepository::findByIdForUpdate(1)
  - Asserts an active transaction exists
  - SELECT ... FOR UPDATE (pessimistic write lock)
  - Returns locked ProductModel

↓ ProductRepository::update(1, UnpersistedProduct)
  - Wrapped in its own DB transaction with lockForUpdate
  - Updates attributes and saves
  - Returns updated Product DTO

↓ HTTP 200 OK
```

### Delete Operation Flow

```
DELETE /api/v1/products/1

↓ DeleteProductController::destroy()

↓ ProductService::deleteProduct(1)

↓ ProductRepository::delete(1)
  - Find product
  - Delete from database

↓ HTTP 200 OK
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
│   └── GET /categories, GET /categories/{id}, GET /categories/{id}/subcategories
│
├── auth.required
│   ├── POST   /orders          (place order)
│   ├── GET    /orders          (scoped to own orders for non-admins)
│   ├── GET    /orders/{id}     (own order only for non-admins)
│   └── PUT    /orders/{id}     (restricted transitions for non-admins)
│
└── admin.required
    ├── POST|PUT|DELETE /products    (CreateProductController now dispatches CreateProductCommand)
    ├── GET /products/{id}/inventory-history
    ├── POST|PUT|DELETE /categories
    └── DELETE /orders/{id}
```

### Controller-level Ownership Checks

Routes under `auth.required` apply further checks inside the controller to enforce resource ownership:

- **`GetOrderController`** — regular users receive `400 Bad Request` if `order.user_id ≠ auth.id`.
- **`UpdateOrderController`** — regular users receive `400 Bad Request` if `order.user_id ≠ auth.id`.
- **`ListOrdersController`** — the `user_id` filter is silently injected for non-admin requests so users can never retrieve another user's orders.

---

## Order Placement & Inventory

Placing an order (`POST /orders`) is not just a record insert — it **atomically updates stock** for every ordered item.

### Flow

```
POST /api/v1/orders
{
  "status": "pending",
  "total_price": 199.98,
  "items": [
    { "product_id": 1, "quantity": 2, "unit_price": 99.99 }
  ]
}

↓ auth.required middleware (401 if unauthenticated)

↓ CreateOrderRequest (validates fields, checks product IDs exist)

↓ CreateOrderController::store()

↓ OrderService::createOrder(UnpersistedOrder)
  DB::transaction {
    for each item:
      1. ProductRepository::findByIdForUpdate(productId)
         → SELECT ... FOR UPDATE   (pessimistic write lock)
         → throws ProductNotFoundException if product missing

      2. Check previousQuantity >= item.quantity
         → throws InsufficientStockException if not enough stock

      3. ProductModel::update(['quantity' => newQuantity])
         → decrements product stock in-place

      4. InventoryHistoryRepository::record(
           change_type   = 'sale',
           quantity_changed = -item.quantity,
           previous_quantity = previousQuantity,
           new_quantity  = newQuantity,
         )

    5. OrderRepository::persist(UnpersistedOrder)
       → inserts order + order_items rows

    commit (or rollback everything on any failure)
  }

↓ HTTP 201 Created
```

### Why a single transaction?

All five steps above run inside one `DB::transaction`. If any product has insufficient stock or any insert fails, the entire transaction is rolled back — no partial orders, no phantom stock decrements.

### Pessimistic locking

`ProductRepository::findByIdForUpdate` issues a `SELECT … FOR UPDATE` lock. This serialises concurrent `POST /orders` requests for the same product, eliminating the race condition where two requests both read the same available quantity and both believe they can fulfil the order.

### Inventory history

Every stock movement is recorded in `inventory_history`:

| Event | `change_type` | `quantity_changed` |
|-------|---------------|--------------------|
| Product created | `addition` | `+initialQty` |
| Admin adjusts quantity | `adjustment` | `±delta` |
| Order placed | `sale` | `-orderedQty` |

---

## Order Status Machine

`app/Services/Order/OrderStatusMachine.php`

### Why a dedicated class?

The transition rules are domain logic — they belong neither in the controller (HTTP layer) nor in the repository (persistence layer). Extracting them into their own class gives us:

- **Single responsibility** — `OrderService` orchestrates, `OrderStatusMachine` decides what is allowed.
- **Testability** — the machine can be unit-tested in complete isolation from the database, HTTP stack, or any other service.
- **Replaceability** — swap transition rules (e.g. add a `hold` status, change the cancellation window) in exactly one file without touching anything else.
- **Mockability** — `OrderService` tests mock the machine, so they never need to reproduce the full transition graph; the machine tests own that coverage.

### Order lifecycle

```
                  ┌──────────┐
         ┌────────│  pending  │──────────────────────┐
         │        └──────────┘                        │
         │ (admin) paid          (admin/user*) cancelled
         ▼                                            ▼
    ┌─────────┐                              ┌─────────────┐
    │  paid   │                              │  cancelled  │ ◄─ terminal
    └─────────┘                              └─────────────┘
     │       │
     │       │ (admin) refunded
     │       ▼
     │   ┌──────────┐
     │   │ refunded │ ◄─ terminal
     │   └──────────┘
     │
     │ (admin) shipped
     ▼
┌──────────┐
│ shipped  │
└──────────┘
     │
     │ (admin) delivered
     ▼
┌───────────┐
│ delivered │
└───────────┘
     │
     │ (admin) refunded
     ▼
┌──────────┐
│ refunded │ ◄─ terminal
└──────────┘
```

*`pending → cancelled` is only permitted for regular users within **24 hours** of order creation.

### Two rule sets, two methods

The machine exposes two methods with different levels of permission:

#### `assertUserTransitionAllowed(Order $existing, OrderStatus $newStatus)`

Enforces what a **regular customer** may request. Currently only one transition is permitted:

| From | To | Extra condition |
|------|----|-----------------|
| `pending` | `cancelled` | Must be within 24 hours of `created_at` |

Any other transition throws `InvalidOrderStateException` → `400 Bad Request`.

#### `assertAdminTransitionAllowed(Order $existing, OrderStatus $newStatus)`

Enforces the **complete lifecycle** for operators. Every valid transition:

| From | To |
|------|----|
| `pending` | `paid`, `cancelled` |
| `paid` | `shipped`, `refunded` |
| `shipped` | `delivered` |
| `delivered` | `refunded` |
| `cancelled` | *(terminal — no outgoing transitions)* |
| `refunded` | *(terminal — no outgoing transitions)* |

Admins go through the machine too — they are not exempt. This means attempting to skip a step (e.g. `paid → delivered`) or resurrect a terminal order (`cancelled → pending`) throws the same `InvalidOrderStateException`.

### Class anatomy

```php
class OrderStatusMachine
{
    // ── constants ──────────────────────────────────────────────────────────
    private const CANCELLATION_WINDOW_HOURS = 24;

    // Indexed by current status value; value is the list of permitted targets.
    private const USER_ALLOWED_TRANSITIONS  = [ ... ];
    private const ADMIN_ALLOWED_TRANSITIONS = [ ... ];

    // ── public API ─────────────────────────────────────────────────────────
    public function assertUserTransitionAllowed(Order $existing, OrderStatus $newStatus): void
    public function assertAdminTransitionAllowed(Order $existing, OrderStatus $newStatus): void
}
```

Both methods throw `InvalidOrderStateException` on violation and return `void` on success — the "tell, don't ask" pattern.

### Integration

```
PUT /api/v1/orders/{id}

UpdateOrderController::executeRequest()
  ├── 1. Auth / ownership check (non-admin users only)
  ├── 2. Resolve isAdmin = auth()->user()->isAdmin()
  └── OrderService::updateOrder(id, unpersisted, asAdmin: isAdmin)
        ├── 3. OrderRepository::findById(id)           ← always fetches existing order
        ├── 4a. if asAdmin  → statusMachine::assertAdminTransitionAllowed(existing, newStatus)
        │   4b. if !asAdmin → statusMachine::assertUserTransitionAllowed(existing, newStatus)
        │         └── also checks 24-hour cancellation window
        └── 5. OrderRepository::update(id, unpersisted)
```

`OrderService` always fetches the existing order (step 3) regardless of role — both rule sets need the current status to validate the transition.

### Adding a new transition

1. Add the new `OrderStatus` case to `app/Enums/OrderStatus.php` if needed.
2. Add the transition to `ADMIN_ALLOWED_TRANSITIONS` (and `USER_ALLOWED_TRANSITIONS` if customers should also trigger it).
3. Add a migration if the `status` column type needs updating.
4. Add a test case to `tests/Unit/Services/OrderStatusMachineTest.php`.

No other file needs to change.

---

## Caching

The application uses Laravel's `Cache` facade with **tagged caching** to reduce database load for read-heavy, infrequently-mutated data. Cache invalidation is performed in the service layer after every successful write operation.

### Configuration

| Setting | Value | Notes |
|---------|-------|-------|
| Default store | `array` (in-memory) | Configured via `CACHE_STORE` env var |
| Production recommendation | `redis` | Persists across requests, supports tags |
| Config file | `config/cache.php` | `redis` store is pre-configured |

The `array` driver stores cache in-memory for the lifetime of a single request/process. For cross-request caching in production, set `CACHE_STORE=redis` in `.env`.

### What is cached

#### Categories (tag: `categories`, TTL: 30 minutes)

| Repository method | Cache key pattern | Cached? |
|-------------------|-------------------|---------|
| `getAll()` | `categories.all.{md5(params)}` | ✅ |
| `findById()` | `categories.{id}` | ✅ |
| `findByName()` | `categories.name.{md5(name)}` | ✅ |
| `getSubcategoriesById()` | `categories.{id}.children` | ✅ |
| `persist()` | — | ❌ |
| `update()` | — | ❌ |
| `delete()` | — | ❌ |

**Invalidation:** `CategoryService` calls `Cache::tags(['categories'])->flush()` after `createCategory()`, `updateCategory()`, and `deleteCategory()`.

#### Products (tag: `products`, TTL: 5 minutes)

| Repository method | Cache key pattern | Cached? |
|-------------------|-------------------|---------|
| `getAll()` | `products.all.{md5(params)}` | ✅ |
| `findById()` | `products.{id}` | ✅ |
| `findByCategoryId()` | `products.category.{categoryId}` | ✅ |
| `findByIdForUpdate()` | — | ❌ (pessimistic lock, must hit DB) |
| `findByName()` | — | ❌ |
| `persist()` | — | ❌ |
| `update()` | — | ❌ |
| `delete()` | — | ❌ |

**Invalidation:**
- `ProductService` calls `Cache::forget("products.{$id}")` + `Cache::tags(['products'])->flush()` after `updateProduct()` and `deleteProduct()`.
- `ProductService` calls `Cache::tags(['products'])->flush()` after `createProduct()`.
- `OrderService` calls `Cache::forget("products.{$id}")` for each ordered product + `Cache::tags(['products'])->flush()` after `createOrder()` (because order placement decrements stock).

### What is NOT cached

| Resource | Reason |
|----------|--------|
| **Orders** | User-specific, real-time state changes, not publicly browsed |
| **Inventory history** | Append-only log, typically only viewed by admins |
| **`findByIdForUpdate()`** | Uses `SELECT … FOR UPDATE` pessimistic lock — must always hit the DB |

### Cache key strategy

List endpoints (`getAll`) accept dynamic parameters (page, perPage, sort, order, filters, includes). The cache key is built using `md5(serialize($params))` to produce a deterministic key per unique parameter combination without key explosion.

### Invalidation strategy

The application uses **tag-based cache flushing** rather than individual key deletion for list caches. When any write occurs:

1. Specific item keys are forgotten via `Cache::forget("resource.{$id}")`
2. The entire tag group is flushed via `Cache::tags(['resource'])->flush()`

This ensures no stale list data is served, even when the exact list cache keys are unknown at invalidation time.

### Important: cache is only flushed on success

If a write operation throws an exception (e.g., `ProductAlreadyExistsException`, `CategoryNotFoundException`), the cache is **not** flushed — the data hasn't changed, so the cache remains valid.

---

## Audit Logging

All create, update, and delete operations are recorded to a dedicated audit log for traceability and compliance.

### Implementation

**Location:** `app/Services/AuditLogger.php`

The `AuditLogger` is a singleton service injected into all domain services (`ProductService`, `CategoryService`, `OrderService`). It writes structured log entries to a dedicated `audit` channel after every successful mutation.

### What is logged

| Service | Actions logged |
|---------|---------------|
| **ProductService** | `product.created`, `product.updated`, `product.deleted` |
| **CategoryService** | `category.created`, `category.updated`, `category.deleted` |
| **OrderService** | `order.created`, `order.updated`, `order.deleted` |

### Log entry structure

Each audit log entry contains:

| Field | Description |
|-------|-------------|
| `action` | The operation performed (e.g. `product.created`) |
| `entity` | The entity type (e.g. `product`, `order`) |
| `entity_id` | The ID of the affected entity |
| `user_id` | The authenticated user who performed the action (null if unauthenticated) |
| `properties` | Action-specific data (e.g. name, price, status changes) |
| `ip` | The IP address of the request |
| `timestamp` | ISO 8601 timestamp of the action |

### Log channel configuration

The audit log writes to a separate daily file at `storage/logs/audit.log` with 90-day retention:

```php
// config/logging.php
'audit' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/audit.log'),
    'level'  => 'info',
    'days'   => 90,
],
```

### Example log entries

**Product created:**
```
[2026-03-04T10:30:00+00:00] local.INFO: product.created {"entity":"product","entity_id":42,"user_id":1,"action":"product.created","properties":{"name":"Gaming Laptop","price":1299.99,"quantity":10},"ip":"127.0.0.1","timestamp":"2026-03-04T10:30:00+00:00"}
```

**Order status updated:**
```
[2026-03-04T11:00:00+00:00] local.INFO: order.updated {"entity":"order","entity_id":7,"user_id":1,"action":"order.updated","properties":{"previous_status":"pending","new_status":"paid","total_price":299.99,"as_admin":true},"ip":"127.0.0.1","timestamp":"2026-03-04T11:00:00+00:00"}
```

### Design decisions

- **Service layer, not middleware:** Audit logs are written from the service layer (after successful mutations) rather than as HTTP middleware. This ensures only successful operations are logged and the log captures domain-level context (e.g. previous status, quantity changes).
- **After success only:** If a mutation throws (e.g. `InsufficientStockException`), no audit entry is written — nothing changed.
- **Dedicated channel:** Audit logs are separated from application logs for easy searching, retention policies, and potential forwarding to external audit systems.

---


### Exception Hierarchy

```
Exception
├── ProductNotFoundException
├── ProductAlreadyExistsException
├── CategoryNotFoundException
├── CategoryAlreadyExistsException
├── OrderNotFoundException
├── InsufficientStockException
├── InvalidOrderStateException
├── BadRequestException
├── UnprocessableEntityException
└── ValidationException (Laravel)
```

### Exception Handling Flow

```
1. Exception thrown in Service/Repository
   throw new ProductNotFoundException($id);

2. Exception handler catches it
   (App/Exceptions/Handler.php)

3. Handler formats response
   {
     "success": false,
     "message": "Product not found"
   }

4. HTTP error response
   404 Not Found
```

### Custom Exception Example

```php
class ProductNotFoundException extends Exception
{
    public function __construct(int $id)
    {
        parent::__construct("Product with ID {$id} not found");
        $this->code = 404;
    }
}
```

---

## Extension Points

### Adding a New CQRS Command

The system uses a lightweight **CQRS command bus** for admin write operations. Adding a new command requires four steps:

1. **Create the command** — an immutable value object in `app/CQRS/Commands/<Domain>/`:
   ```php
   final readonly class PublishProductCommand implements CommandInterface
   {
       public function __construct(public int $productId) {}
   }
   ```

2. **Create the handler** — in `app/CQRS/Handlers/<Domain>/`:
   ```php
   readonly class PublishProductCommandHandler implements CommandHandlerInterface
   {
       public function __construct(private ProductServiceInterface $productService) {}

       public function handle(CommandInterface $command): Product
       {
           return $this->productService->publishProduct($command->productId);
       }
   }
   ```

3. **Register in `AppServiceProvider`** — add to the `CommandBus` handler map:
   ```php
   PublishProductCommand::class => PublishProductCommandHandler::class,
   ```

4. **Add the admin route** in `routes/api.php` under the `admin.required` group:
   ```php
   Route::post('admin/products/{id}/publish', [AdminPublishProductController::class, 'store'])
       ->name('v1.admin.products.publish');
   ```

---

### Adding a New Resource

1. **Create Model**
   ```php
   // app/Models/Resource/ResourceModel.php
   class ResourceModel extends Model { ... }
   ```

2. **Create DTO**
   ```php
   // app/Dto/Resource/Resource.php
   final readonly class Resource { ... }
   ```

3. **Create Repository**
   ```php
   // app/Repositories/Resource/ResourceRepository.php
   class ResourceRepository { ... }
   ```

4. **Create Service**
   ```php
   // app/Services/Resource/ResourceService.php
   final readonly class ResourceService { ... }
   ```

5. **Create Controllers**
   ```php
   // app/Http/Controllers/Api/V1/Resource/ListResourcesController.php
   // app/Http/Controllers/Api/V1/Resource/GetResourceController.php
   // etc.
   ```

6. **Create Requests**
   ```php
   // app/Http/Requests/Resource/ListResourcesRequest.php
   // app/Http/Requests/Resource/CreateResourceRequest.php
   // etc.
   ```

7. **Create Responses**
   ```php
   // app/Http/Responses/Resource/ListResourcesResponse.php
   // etc.
   ```

8. **Create Transformer**
   ```php
   // app/Transformers/ResourceTransformer.php
   ```

9. **Register Routes**
   ```php
   // routes/api.php
   Route::get('resources', [ListResourcesController::class, 'index']);
   // etc.
   ```

10. **Create Tests**
    ```php
    // tests/Feature/Controllers/Resource/ListResourcesControllerTest.php
    // tests/Unit/Services/Resource/ResourceServiceTest.php
    ```

---

## Best Practices

### 1. Repository Pattern
- Use repositories for all database access
- Keep repositories focused on data access
- Make repositories easy to mock for testing

### 2. Service Layer
- Put business logic in services, not controllers
- Use dependency injection
- Keep services focused and single-responsibility

### 3. DTOs
- Use readonly properties for immutability
- Create factory methods (fromModel)
- Keep DTOs focused on data

### 4. Validation
- Use Form Requests for all input validation
- Validate in request, not in service
- Provide clear error messages

### 5. Error Handling
- Use custom exceptions
- Handle exceptions at controller level
- Return consistent error responses

### 6. Concurrency & Inventory Locking
- All inventory mutations (create, update) run inside `DB::transaction`
- `ProductRepository::findByIdForUpdate` issues a `SELECT ... FOR UPDATE` (pessimistic write lock) to serialize concurrent stock changes
- The method asserts it is called inside an active transaction (`DB::transactionLevel() > 0`)
- `InsufficientStockException` is thrown when a requested quantity would result in a negative stock level
- Both the service-level outer transaction and the repository-level inner transaction use `lockForUpdate`, ensuring correctness whether `update` is called directly or via the service

### 7. Testing
- Test services with mocked repositories
- Test controllers with fixtures
- Test repositories with test database
- Test caching behavior (cache hits, invalidation on writes, no-flush on errors)

### 8. Caching
- Cache read-heavy, infrequently-mutated data (categories, products)
- Use tagged caching for bulk invalidation
- Invalidate in the service layer after successful writes only
- Never cache data accessed with pessimistic locks (`findByIdForUpdate`)
- Use short TTLs for data that changes with transactional operations (e.g. product stock)

### 9. Code Organization
- Keep files organized by resource
- Use consistent naming conventions
- Keep classes focused and single-responsibility

---

This architecture ensures:
- ✅ Clean code
- ✅ Easy testing
- ✅ Maintainability
- ✅ Scalability
- ✅ Reusability

