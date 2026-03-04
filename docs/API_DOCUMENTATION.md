# Shop API - Complete API Reference

## Table of Contents
1. [Base URL & Authentication](#base-url--authentication)
2. [Response Format](#response-format)
3. [Endpoints Overview](#endpoints-overview)
4. [Products Endpoints](#products-endpoints)
5. [Categories Endpoints](#categories-endpoints)
6. [Orders Endpoints](#orders-endpoints)
7. [Error Handling](#error-handling)
8. [Pagination](#pagination)
9. [Filtering](#filtering)
10. [Sorting](#sorting)

---

## Base URL & Authentication

### Base URL
```
http://localhost:8000/api/v1/
```

### Authentication

The API uses Laravel's session/token-based authentication. Requests are authenticated by passing a valid session cookie or Bearer token issued at login.

```
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

### Roles

| Role | Description |
|------|-------------|
| **Guest** | Unauthenticated — no token required |
| **User** | Authenticated regular customer |
| **Admin** | Authenticated user with `role = admin` |

### Access Control (RBAC)

| Endpoint | Guest | User | Admin |
|----------|:-----:|:----:|:-----:|
| **Products** |
| `GET /products` | ✅ | ✅ | ✅ |
| `GET /products/{id}` | ✅ | ✅ | ✅ |
| `POST /products` | ❌ | ❌ | ✅ |
| `PUT /products/{id}` | ❌ | ❌ | ✅ |
| `DELETE /products/{id}` | ❌ | ❌ | ✅ |
| `GET /products/{id}/inventory-history` | ❌ | ❌ | ✅ |
| **Categories** |
| `GET /categories` | ✅ | ✅ | ✅ |
| `GET /categories/{id}` | ✅ | ✅ | ✅ |
| `GET /categories/{id}/subcategories` | ✅ | ✅ | ✅ |
| `POST /categories` | ❌ | ❌ | ✅ |
| `PUT /categories/{id}` | ❌ | ❌ | ✅ |
| `DELETE /categories/{id}` | ❌ | ❌ | ✅ |
| **Orders** |
| `GET /orders` | ❌ | ✅ own only | ✅ all |
| `GET /orders/{id}` | ❌ | ✅ own only | ✅ any |
| `POST /orders` | ❌ | ✅ | ✅ |
| `PUT /orders/{id}` | ❌ | ✅ own + restricted | ✅ unrestricted |
| `DELETE /orders/{id}` | ❌ | ❌ | ✅ |

### Auth Error Responses

**`401 Unauthorized`** — request is not authenticated:
```json
{
  "success": false,
  "message": "Unauthenticated.",
  "error": "Unauthorized"
}
```

**`403 Forbidden`** — authenticated but insufficient role:
```json
{
  "success": false,
  "message": "You do not have permission to perform this action.",
  "error": "Forbidden"
}
```

### Common Headers
```
Content-Type: application/json
Accept: application/json
```

---

## Response Format

### Success Response Format
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Product Name",
    "description": "Product description",
    "price": 99.99,
    "quantity": 10,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "message": "Resource found"
}
```

### List Response Format
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Product 1",
      "price": 99.99
    },
    {
      "id": 2,
      "name": "Product 2",
      "price": 49.99
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10
  },
  "message": "Products found"
}
```

### Error Response Format
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "filter.min_price": ["The min price must be numeric"],
    "sort": ["The selected sort is invalid"]
  }
}
```

---

## Endpoints Overview

### All Available Endpoints

| Method | Endpoint | Auth Required | Description |
|--------|----------|---------------|-------------|
| **PRODUCTS** |
| GET | `/products` | — | List all products |
| GET | `/products/{id}` | — | Get single product |
| POST | `/products` | Admin | Create product |
| PUT | `/products/{id}` | Admin | Update product |
| DELETE | `/products/{id}` | Admin | Delete product |
| GET | `/products/{id}/inventory-history` | Admin | Product inventory history |
| **CATEGORIES** |
| GET | `/categories` | — | List all categories |
| GET | `/categories/{id}` | — | Get single category |
| GET | `/categories/{id}/subcategories` | — | List subcategories |
| POST | `/categories` | Admin | Create category |
| PUT | `/categories/{id}` | Admin | Update category |
| DELETE | `/categories/{id}` | Admin | Delete category |
| **ORDERS** |
| GET | `/orders` | User / Admin | List orders (users see own only) |
| GET | `/orders/{id}` | User / Admin | Get order (users see own only) |
| POST | `/orders` | User / Admin | Place order |
| PUT | `/orders/{id}` | User / Admin | Update order (users: restricted transitions) |
| DELETE | `/orders/{id}` | Admin | Delete order |

---

## Products Endpoints

### List All Products
```
GET /api/v1/products
```

**Query Parameters:**
```
page=1                              (optional, default: 1)
per_page=15                         (optional, default: 15, max: 100)
sort=id|name|price|quantity|created_at|updated_at  (optional, default: id)
order=asc|desc                      (optional, default: asc)
filter[name]=text                   (optional, substring search on name)
filter[search]=text                 (optional, substring search on name OR description)
filter[category_id]=1               (optional, exact match)
filter[category_ids]=1,3,7          (optional, OR match across multiple categories)
filter[min_price]=100               (optional, numeric)
filter[max_price]=1000              (optional, numeric)
filter[min_quantity]=5              (optional, integer)
filter[max_quantity]=100            (optional, integer)
include=category                    (optional, load related)
```

**Example Request:**
```bash
GET /api/v1/products?filter[category_id]=1&filter[min_price]=100&sort=price&order=asc&page=1&per_page=10
```

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Gaming Laptop",
      "description": "High-performance laptop",
      "price": 1299.99,
      "quantity": 5,
      "category_id": 1,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 25,
    "last_page": 3
  },
  "message": "Products found"
}
```

**Status Code:** `200 OK`

---

### Get Single Product
```
GET /api/v1/products/{id}
```

**Example Request:**
```bash
GET /api/v1/products/1
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Gaming Laptop",
    "description": "High-performance laptop",
    "price": 1299.99,
    "quantity": 5,
    "category_id": 1,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "message": "Product found"
}
```

**Status Code:** `200 OK`

**Error Response (404):**
```json
{
  "success": false,
  "message": "Product not found"
}
```

---

### Create Product
```
POST /api/v1/products
```

**Request Body:**
```json
{
  "name": "New Product",
  "description": "Product description",
  "price": 99.99,
  "quantity": 10,
  "category_id": 1
}
```

**Validation Rules:**
- `name` - Required, string, unique
- `description` - Required, string
- `price` - Required, numeric, min: 0
- `quantity` - Required, integer, min: 0
- `category_id` - Required, integer, exists in categories table

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 100,
    "name": "New Product",
    "description": "Product description",
    "price": 99.99,
    "quantity": 10,
    "category_id": 1,
    "created_at": "2024-01-20T15:00:00Z",
    "updated_at": "2024-01-20T15:00:00Z"
  },
  "message": "Product created successfully"
}
```

**Status Code:** `201 Created`

---

### Update Product
```
PUT /api/v1/products/{id}
```

**Request Body:**
```json
{
  "name": "Updated Product Name",
  "description": "Updated description",
  "price": 149.99,
  "quantity": 15,
  "category_id": 2
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Updated Product Name",
    "description": "Updated description",
    "price": 149.99,
    "quantity": 15,
    "category_id": 2,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-20T15:00:00Z"
  },
  "message": "Product updated successfully"
}
```

**Status Code:** `200 OK`

---

### Delete Product
```
DELETE /api/v1/products/{id}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```

**Status Code:** `200 OK`

---

## Categories Endpoints

### List All Categories
```
GET /api/v1/categories
```

**Query Parameters:**
```
page=1                              (optional, default: 1)
per_page=15                         (optional, default: 15, max: 100)
sort=id|name|created_at|updated_at  (optional, default: id)
order=asc|desc                      (optional, default: asc)
filter[name]=text                   (optional, substring search)
filter[parent_id]=1                 (optional, exact match or null)
include=children                    (optional, load subcategories)
```

**Example Request:**
```bash
GET /api/v1/categories?filter[parent_id]=null&include=children&sort=name
```

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Electronics",
      "parent_id": null,
      "children": [
        {
          "id": 2,
          "name": "Computers",
          "parent_id": 1
        }
      ],
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5,
    "last_page": 1
  },
  "message": "Categories found"
}
```

**Status Code:** `200 OK`

---

### Get Single Category
```
GET /api/v1/categories/{id}
```

**Status Code:** `200 OK`

---

### Create Category
```
POST /api/v1/categories
```

**Request Body:**
```json
{
  "name": "New Category",
  "parent_id": null
}
```

**Validation Rules:**
- `name` - Required, string, unique
- `parent_id` - Optional, integer, exists in categories table

**Status Code:** `201 Created`

---

### Update Category
```
PUT /api/v1/categories/{id}
```

**Request Body:**
```json
{
  "name": "Updated Category",
  "parent_id": 1
}
```

**Status Code:** `200 OK`

---

### Delete Category
```
DELETE /api/v1/categories/{id}
```

**Status Code:** `200 OK`

---

### List Subcategories
```
GET /api/v1/categories/{id}/subcategories
```

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Computers",
      "parent_id": 1,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "message": "Subcategories found"
}
```

**Status Code:** `200 OK`

---

## Orders Endpoints

### List All Orders
```
GET /api/v1/orders
```

**Auth:** User or Admin (required)

> **Scoping:** Regular users only receive their own orders. Admins receive all orders. The `user_id` filter is automatically injected for non-admin requests and cannot be overridden.

**Query Parameters:**
```
page=1                              (optional, default: 1)
per_page=15                         (optional, default: 15, max: 100)
sort=id|status|total_price|created_at|updated_at  (optional)
order=asc|desc                      (optional, default: asc)
filter[status]=pending              (optional, single or comma-separated: pending,paid)
filter[min_total]=100               (optional)
filter[max_total]=5000              (optional)
include=items                       (optional, load order items)
```

**Example Request:**
```bash
GET /api/v1/orders?filter[status]=pending&filter[min_total]=100&sort=created_at&order=desc
```

**Status Code:** `200 OK`

---

### Get Single Order
```
GET /api/v1/orders/{id}
```

**Auth:** User or Admin (required)

> **Ownership:** Regular users receive `400 Bad Request` if the order does not belong to them. Admins can retrieve any order.

**Status Code:** `200 OK`

---

### Create Order (Place Order)
```
POST /api/v1/orders
```

**Auth:** User or Admin (required)

**Request Body:**
```json
{
  "status": "pending",
  "total_price": 299.99,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 99.99
    }
  ]
}
```

**Validation Rules:**
- `status` — Required, one of: `pending`, `paid`, `shipped`, `delivered`, `cancelled`, `refunded`
- `total_price` — Required, numeric, min: 0
- `items` — Required, array, min 1 item
- `items.*.product_id` — Required, integer, must exist in products table
- `items.*.quantity` — Required, integer, min: 1, max: 10000
- `items.*.unit_price` — Required, numeric, min: 0

**Inventory side-effect:**

Placing an order **atomically deducts stock** for every item inside a single database transaction:

1. Each product row is locked with `SELECT … FOR UPDATE` to prevent overselling under concurrent requests.
2. Available stock is validated against the requested quantity.
3. The product `quantity` column is decremented.
4. An `inventory_history` record with `change_type = sale` is written.
5. The order and its items are inserted.

If any product has insufficient stock the entire transaction is rolled back and no order is created.

**Status Code:** `201 Created`

**Error — Insufficient stock (`400 Bad Request`):**
```json
{
  "success": false,
  "message": "Insufficient stock for product 1: requested 5, available 2.",
  "error": "Bad Request"
}
```

---

### Update Order
```
PUT /api/v1/orders/{id}
```

**Auth:** User or Admin (required)

> **Ownership:** Regular users receive `400 Bad Request` if the order does not belong to them.

#### Status Machine

All status updates — user and admin alike — are validated by `OrderStatusMachine`.

**Regular users** may only perform:

| From | To | Extra condition |
|------|----|-----------------|
| `pending` | `cancelled` | Must be within **24 hours** of order creation |

**Admins** may perform any transition in the full lifecycle:

| From | To |
|------|----|
| `pending` | `paid`, `cancelled` |
| `paid` | `shipped`, `refunded` |
| `shipped` | `delivered` |
| `delivered` | `refunded` |
| `cancelled` | *(terminal)* |
| `refunded` | *(terminal)* |

Attempting an invalid transition (including skipping steps or moving out of a terminal status) throws `InvalidOrderStateException` → `400 Bad Request` for both roles.

**Request Body:**
```json
{
  "status": "cancelled",
  "total_price": 299.99,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 99.99
    }
  ]
}
```

**Status Code:** `200 OK`

**Error — Invalid transition (`400 Bad Request`):**
```json
{
  "success": false,
  "message": "Transition from 'pending' to 'shipped' is not allowed.",
  "error": "Bad Request"
}
```

**Error — Cancellation window expired (`400 Bad Request`):**
```json
{
  "success": false,
  "message": "Orders can only be cancelled within 24 hours of creation.",
  "error": "Bad Request"
}
```

---

### Delete Order
```
DELETE /api/v1/orders/{id}
```

**Auth:** Admin only

**Status Code:** `204 No Content`

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Successfully retrieved/updated resource |
| 201 | Created | Resource created successfully |
| 204 | No Content | Resource deleted successfully |
| 400 | Bad Request | Business rule violation (e.g. insufficient stock, invalid order transition) |
| 401 | Unauthorized | Request is not authenticated |
| 403 | Forbidden | Authenticated but insufficient role |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal server error |

### Error Response Examples

**Validation Error (422)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required"],
    "price": ["The price must be numeric"]
  }
}
```

**Resource Not Found (404)**
```json
{
  "success": false,
  "message": "Product not found"
}
```

**Invalid Filters (422)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "filter.min_price": ["The min price must be numeric"],
    "sort": ["The selected sort is invalid"]
  }
}
```

---

## Pagination

### Pagination Parameters

```
page=N                  Page number (default: 1, min: 1)
per_page=N             Items per page (default: 15, min: 1, max: 100)
```

### Pagination Response Metadata

```json
{
  "meta": {
    "current_page": 2,
    "per_page": 10,
    "total": 150,
    "last_page": 15
  }
}
```

### Pagination Examples

**Get page 2 with 20 items per page:**
```bash
GET /api/v1/products?page=2&per_page=20
```

**Navigate to last page:**
```bash
# Get first page to see total
GET /api/v1/products?page=1

# Then navigate to last_page value returned in meta
GET /api/v1/products?page=15
```

---

## Filtering

### Filter Syntax

```
filter[field]=value
filter[field1]=value1&filter[field2]=value2
```

### Filter Types by Resource

**Products**
```
filter[name]=text           - Substring search (name only)
filter[search]=text         - Substring search (name OR description)
filter[category_id]=1       - Exact integer match (single category)
filter[category_ids]=1,3,7  - OR match (products in any of the listed categories)
filter[min_price]=100       - Numeric range start
filter[max_price]=1000      - Numeric range end
filter[min_quantity]=5      - Integer range start
filter[max_quantity]=100    - Integer range end
```

**Categories**
```
filter[name]=text           - Substring search
filter[parent_id]=1         - Exact match (use null for root)
```

**Orders**
```
filter[status]=pending      - Single status match
filter[status]=pending,paid - OR match (orders in any of the listed statuses)
filter[min_total]=100       - Numeric range start
filter[max_total]=5000      - Numeric range end
```

### Filter Examples

**Search products by name or description:**
```bash
GET /api/v1/products?filter[search]=laptop
```

**Products in multiple categories:**
```bash
GET /api/v1/products?filter[category_ids]=1,3,7
```

**Complex product query:**
```bash
GET /api/v1/products?filter[category_ids]=1,3&filter[search]=pro&filter[min_price]=500&filter[max_price]=2000
```

**Category hierarchy:**
```bash
GET /api/v1/categories?filter[parent_id]=null   # Root categories
GET /api/v1/categories?filter[parent_id]=1      # Children of category 1
```

**Orders with multiple statuses (actionable orders):**
```bash
GET /api/v1/orders?filter[status]=pending,paid
```

**Order filtering:**
```bash
GET /api/v1/orders?filter[status]=pending&filter[min_total]=100
```

---

## Sorting

### Sort Syntax

```
sort=field&order=asc|desc
```

### Sort Fields by Resource

**Products**
```
sort=id | name | price | quantity | created_at | updated_at
```

**Categories**
```
sort=id | name | created_at | updated_at
```

**Orders**
```
sort=id | status | total_price | created_at | updated_at
```


### Sort Examples

**Sort products by price (ascending):**
```bash
GET /api/v1/products?sort=price&order=asc
```

**Sort products by price (descending):**
```bash
GET /api/v1/products?sort=price&order=desc
```

**Sort orders by most recent:**
```bash
GET /api/v1/orders?sort=created_at&order=desc
```

**Sort categories by name:**
```bash
GET /api/v1/categories?sort=name&order=asc
```

---

## Rate Limiting

API requests are rate-limited to prevent abuse.

**Current Limits:**
- **60 requests per minute** per IP address
- Configured in: `app/Providers/AppServiceProvider.php`
- Applied to all `/api/v1/*` routes

**Rate Limit Response Headers:**
```
RateLimit-Limit: 60
RateLimit-Remaining: 45
RateLimit-Reset: 1709136000
```

**When Limit Exceeded:**
- **HTTP Status:** `429 Too Many Requests`
- **Response:**
  ```json
  {
    "message": "Too Many Attempts."
  }
  ```
- **Retry-After Header:** Seconds until limit resets

**Example:**
```bash
# Normal request
curl -i http://localhost:8000/api/v1/products

# Response headers include:
# RateLimit-Limit: 60
# RateLimit-Remaining: 59

# After 60 requests in 1 minute:
# HTTP/1.1 429 Too Many Requests
# Retry-After: 42
```

---

## Common API Workflows

### Get Products in a Category

```bash
# Get products in Electronics category (id=1), sorted by price
GET /api/v1/products?filter[category_id]=1&sort=price&order=asc
```

### Browse Category Hierarchy

```bash
# Get root categories
GET /api/v1/categories?filter[parent_id]=null

# Get subcategories
GET /api/v1/categories?filter[parent_id]=1
```

### Search Products

```bash
# Search for laptops in a price range
GET /api/v1/products?filter[name]=laptop&filter[min_price]=500&filter[max_price]=2000
```

### View Recent Orders

```bash
# Get most recent orders
GET /api/v1/orders?sort=created_at&order=desc&page=1&per_page=10
```


---

This documentation covers all endpoints, parameters, and examples. For more details on specific resources, refer to the individual resource documentation files.


