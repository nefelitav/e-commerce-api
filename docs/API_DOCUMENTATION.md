# Shop API - Complete API Reference

## Table of Contents
1. [Base URL & Authentication](#base-url--authentication)
2. [Response Format](#response-format)
3. [Endpoints Overview](#endpoints-overview)
4. [Products Endpoints](#products-endpoints)
5. [Categories Endpoints](#categories-endpoints)
6. [Orders Endpoints](#orders-endpoints)
7. [Carts Endpoints](#carts-endpoints)
8. [Error Handling](#error-handling)
9. [Pagination](#pagination)
10. [Filtering](#filtering)
11. [Sorting](#sorting)
3. [Endpoints Overview](#endpoints-overview)
4. [Products Endpoints](#products-endpoints)
5. [Categories Endpoints](#categories-endpoints)
6. [Orders Endpoints](#orders-endpoints)
7. [Carts Endpoints](#carts-endpoints)
8. [Error Handling](#error-handling)
9. [Pagination](#pagination)
10. [Filtering](#filtering)
11. [Sorting](#sorting)

---

## Base URL & Authentication

### Base URL
```
http://localhost:8000/api/v1/
```

### Authentication
Currently, the API does not require authentication. Future versions will include OAuth2 or JWT.

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

| Method | Endpoint | Description |
|--------|----------|-------------|
| **PRODUCTS** |
| GET | `/products` | List all products |
| GET | `/products/{id}` | Get single product |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Delete product |
| GET | `/products/{id}/inventory-history` | Product inventory history |
| **CATEGORIES** |
| GET | `/categories` | List all categories |
| GET | `/categories/{id}` | Get single category |
| POST | `/categories` | Create category |
| PUT | `/categories/{id}` | Update category |
| DELETE | `/categories/{id}` | Delete category |
| GET | `/categories/{id}/subcategories` | List subcategories |
| **ORDERS** |
| GET | `/orders` | List all orders |
| GET | `/orders/{id}` | Get single order |
| POST | `/orders` | Create order |
| PUT | `/orders/{id}` | Update order |
| DELETE | `/orders/{id}` | Delete order |
| **CARTS** |
| GET | `/carts` | List all carts |
| GET | `/carts/{id}` | Get single cart |
| POST | `/carts` | Create cart |
| PUT | `/carts/{id}` | Update cart |
| DELETE | `/carts/{id}` | Delete cart |

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
filter[name]=text                   (optional, substring search)
filter[category_id]=1               (optional, exact match)
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

**Query Parameters:**
```
page=1                              (optional, default: 1)
per_page=15                         (optional, default: 15, max: 100)
sort=id|status|total_price|created_at|updated_at  (optional)
order=asc|desc                      (optional, default: asc)
filter[status]=pending              (optional)
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

**Status Code:** `200 OK`

---

### Create Order
```
POST /api/v1/orders
```

**Request Body:**
```json
{
  "status": "pending",
  "total_price": 299.99,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "price": 99.99
    }
  ]
}
```

**Status Code:** `201 Created`

---

### Update Order
```
PUT /api/v1/orders/{id}
```

**Request Body:**
```json
{
  "status": "completed",
  "total_price": 299.99
}
```

**Status Code:** `200 OK`

---

### Delete Order
```
DELETE /api/v1/orders/{id}
```

**Status Code:** `200 OK`

---

## Carts Endpoints

### List All Carts
```
GET /api/v1/carts
```

**Query Parameters:**
```
page=1                              (optional, default: 1)
per_page=15                         (optional, default: 15, max: 100)
sort=id|created_at|updated_at       (optional, default: id)
order=asc|desc                      (optional, default: asc)
include=items                       (optional, load cart items)
```

**Example Request:**
```bash
GET /api/v1/carts?include=items&sort=created_at&order=desc
```

**Status Code:** `200 OK`

---

### Get Single Cart
```
GET /api/v1/carts/{id}
```

**Status Code:** `200 OK`

---

### Create Cart
```
POST /api/v1/carts
```

**Request Body:**
```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

**Status Code:** `201 Created`

---

### Update Cart
```
PUT /api/v1/carts/{id}
```

**Request Body:**
```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 3
    }
  ]
}
```

**Status Code:** `200 OK`

---

### Delete Cart
```
DELETE /api/v1/carts/{id}
```

**Status Code:** `200 OK`

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Successfully retrieved/updated resource |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid query parameters |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
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
filter[name]=text           - Substring search
filter[category_id]=1       - Exact integer match
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
filter[status]=pending      - Exact text match
filter[min_total]=100       - Numeric range start
filter[max_total]=5000      - Numeric range end
```

### Filter Examples

**Complex product query:**
```bash
GET /api/v1/products?filter[category_id]=1&filter[min_price]=500&filter[max_price]=2000&filter[min_quantity]=5
```

**Category hierarchy:**
```bash
GET /api/v1/categories?filter[parent_id]=null   # Root categories
GET /api/v1/categories?filter[parent_id]=1      # Children of category 1
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

**Carts**
```
sort=id | created_at | updated_at
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

### Get User's Cart with Items

```bash
# Get cart with included items
GET /api/v1/carts/{id}?include=items
```

---

This documentation covers all endpoints, parameters, and examples. For more details on specific resources, refer to the individual resource documentation files.


