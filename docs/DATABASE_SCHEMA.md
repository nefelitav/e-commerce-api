# Shop API - Database Schema Documentation

## Table of Contents
1. [Overview](#overview)
2. [Entity Relationship Diagram](#entity-relationship-diagram)
3. [Tables](#tables)
4. [Relationships](#relationships)
5. [Indexes](#indexes)
6. [Migrations](#migrations)

---

## Overview

The Shop API uses a relational database design with the following core tables:

- **users** - User/customer accounts
- **categories** - Product categories (hierarchical)
- **products** - Product catalog
- **orders** - Customer orders
- **order_items** - Items within orders
- **inventory_history** - Inventory change tracking
- **coupons** - Discount coupons
- **return_requests** - Return/refund requests

---

## Entity Relationship Diagram

```
┌──────────────────┐
│     users        │
├──────────────────┤
│ id (PK)          │
│ name             │
│ email            │
│ password         │
│ role             │
│ address_line1    │
│ city             │
│ state            │
│ zip_code         │
│ country          │
│ phone_number     │
│ created_at       │
└────────┬─────────┘
         │
         │ 1:M
         │
         ▼
┌─────────────┐
│   orders    │
└──────┬──────┘
       │
       │ 1:M
       │
       ▼
┌──────────────┐
│ order_items  │
└──────┬───────┘
       │ M:1
       │
       ▼
┌──────────────┐
│  products    │
├──────────────┤
│ id (PK)      │
│ category_id  │
│ name         │
│ description  │
│ price        │
│ quantity     │
└──────┬───────┘
       │
       │ M:1
       │
       ▼
┌──────────────┐
│ categories   │
├──────────────┤
│ id (PK)      │
│ parent_id    │ (self-referencing)
│ name         │
│ description  │
└──────────────┘

┌─────────────────────┐
│ inventory_history   │
├─────────────────────┤
│ id (PK)             │
│ product_id (FK)     │
│ change_type         │
│ quantity_changed    │
│ previous_quantity   │
│ new_quantity        │
│ created_at          │
└─────────────────────┘
```

---

## Tables

### users Table

**Purpose:** Store user/customer information and authentication

**Structure:**
```
Column Name         Type          Constraints           Description
──────────────────────────────────────────────────────────────────────
id                  bigint        PK, Auto-inc          User ID
name                string        NOT NULL              User name
email               string        UNIQUE, NOT NULL      Email address
email_verified_at   timestamp     Nullable              Email verification timestamp
password            string        NOT NULL              Hashed password
remember_token      string(100)   Nullable              Session remember token
role                enum          NOT NULL, DEFAULT user User role (user, admin)
address_line1       string        Nullable              Street address
city                string        Nullable              City
state               string        Nullable              State/province
zip_code            string        Nullable              Postal/zip code
country             string        Nullable              Country
phone_number        string        Nullable              Phone number
created_at          timestamp     DEFAULT NULL          Creation timestamp
updated_at          timestamp     DEFAULT NULL          Last update timestamp
```

**SQL:**
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    address_line1 VARCHAR(255),
    city VARCHAR(255),
    state VARCHAR(255),
    zip_code VARCHAR(255),
    country VARCHAR(255),
    phone_number VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_users_role ON users(role);
```

---

### categories Table

**Purpose:** Store product categories (supports hierarchical structure)

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Category ID
name           string        NOT NULL           Category name
description    text          Nullable           Category description
parent_id      bigint        FK, Nullable       Parent category (cascade delete)
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE categories (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id BIGINT REFERENCES categories(id) ON DELETE CASCADE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Examples:**
```
Electronics (id: 1, parent_id: NULL)
├─ Computers (id: 2, parent_id: 1)
│  ├─ Laptops (id: 3, parent_id: 2)
│  └─ Desktops (id: 4, parent_id: 2)
└─ Accessories (id: 5, parent_id: 1)
```

---

### products Table

**Purpose:** Store product information

**Structure:**
```
Column Name    Type           Constraints        Description
─────────────────────────────────────────────────────────────
id             bigint         PK, Auto-inc       Product ID
name           string         NOT NULL, UNIQUE   Product name
description    text           Nullable           Product description
price          decimal(10,2)  NOT NULL           Product price
quantity       integer        NOT NULL           Stock quantity
category_id    bigint         FK, NOT NULL       Category reference
created_at     timestamp      DEFAULT NULL       Creation timestamp
updated_at     timestamp      DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    quantity INTEGER NOT NULL,
    category_id BIGINT NOT NULL REFERENCES categories(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_products_category_id ON products(category_id);
```

---

### orders Table

**Purpose:** Store customer orders

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Order ID
user_id        bigint        FK, NOT NULL       User reference
status         string        NOT NULL           Order status
total_price    decimal(10,2) NOT NULL           Total order price
coupon_id      bigint        FK, Nullable       Applied coupon reference
discount_amount decimal(10,2) NOT NULL, DEFAULT 0  Discount amount applied
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    user_id BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(255) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    coupon_id BIGINT REFERENCES coupons(id) ON DELETE SET NULL,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
```

**Status Values:**
- `pending` - Order created, awaiting payment
- `paid` - Payment received
- `shipped` - Order sent to customer
- `delivered` - Order received
- `cancelled` - Order cancelled
- `refunded` - Order refunded

---

### order_items Table

**Purpose:** Store individual items within orders

**Structure:**
```
Column Name    Type           Constraints        Description
─────────────────────────────────────────────────────────────
id             bigint         PK, Auto-inc       Item ID
order_id       bigint         FK, NOT NULL       Order reference (cascade delete)
product_id     bigint         FK, NOT NULL       Product reference
quantity       integer        NOT NULL           Quantity ordered
unit_price     decimal(10,2)  NOT NULL           Price per unit at time of order
created_at     timestamp      DEFAULT NULL       Creation timestamp
updated_at     timestamp      DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE order_items (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
```

---

### inventory_history Table

**Purpose:** Track inventory changes for audit and analytics

**Structure:**
```
Column Name        Type          Constraints        Description
──────────────────────────────────────────────────────────────────
id                 bigint        PK, Auto-inc       History ID
product_id         bigint        FK, NOT NULL       Product reference
change_type        enum          NOT NULL, indexed   Type of change (backed by InventoryChangeType enum)
quantity_changed   integer       NOT NULL           Change amount (negative for deductions)
previous_quantity  integer       NOT NULL           Stock before change
new_quantity       integer       NOT NULL           Stock after change
created_at         timestamp     DEFAULT NULL       Creation timestamp
updated_at         timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE inventory_history (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    product_id BIGINT NOT NULL REFERENCES products(id),
    change_type ENUM('addition','removal','sale','return','adjustment','transfer') NOT NULL,
    quantity_changed INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_inventory_history_product_id ON inventory_history(product_id);
CREATE INDEX idx_inventory_history_change_type ON inventory_history(change_type);
```

**Change Type Values (App\Enums\InventoryChangeType):**
- `addition` - Product created or stock added
- `removal` - Stock removed manually
- `sale` - Sold to customer (order placed)
- `return` - Customer return
- `adjustment` - Admin inventory adjustment
- `transfer` - Stock transferred

---

### coupons Table

**Purpose:** Store discount coupons for orders

**Structure:**
```
Column Name       Type           Constraints        Description
──────────────────────────────────────────────────────────────────
id                bigint         PK, Auto-inc       Coupon ID
code              string         UNIQUE, NOT NULL   Coupon code
type              string         NOT NULL           Coupon type (percentage, fixed_amount)
value             decimal(10,2)  NOT NULL           Discount value
min_order_amount  decimal(10,2)  Nullable           Minimum order total to use coupon
max_uses          integer        Nullable           Maximum number of uses
times_used        integer        NOT NULL, DEFAULT 0 Number of times used
expires_at        timestamp      Nullable           Expiration date
is_active         boolean        NOT NULL, DEFAULT true  Whether coupon is active
created_at        timestamp      DEFAULT NULL       Creation timestamp
updated_at        timestamp      DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE coupons (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(255) NOT NULL,
    value DECIMAL(10, 2) NOT NULL,
    min_order_amount DECIMAL(10, 2),
    max_uses INTEGER,
    times_used INTEGER NOT NULL DEFAULT 0,
    expires_at TIMESTAMP,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Coupon Type Values (App\Enums\CouponType):**
- `percentage` - Percentage discount (e.g., 20% off)
- `fixed_amount` - Fixed amount discount (e.g., $10 off)

---

### return_requests Table

**Purpose:** Store return/refund requests from customers

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Request ID
order_id       bigint        FK, NOT NULL       Order reference (cascade delete)
user_id        bigint        FK, NOT NULL       User who submitted (cascade delete)
reason         text          NOT NULL           Reason for return
status         string        NOT NULL, indexed  Request status
admin_notes    text          Nullable           Admin response notes
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE return_requests (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    status VARCHAR(255) NOT NULL,
    admin_notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_return_requests_status ON return_requests(status);
```

**Status Values (App\Enums\ReturnRequestStatus):**
- `pending` - Request submitted, awaiting admin review
- `approved` - Return approved, order refunded, stock restored
- `rejected` - Return request denied

---

## Relationships

### One-to-Many Relationships

#### User → Orders
```
One user can have many orders
users.id → orders.user_id
```

**Query Example:**
```php
$user = User::find(1);
$orders = $user->orders; // Get all user's orders
```

#### Category → Products
```
One category can have many products
categories.id → products.category_id
```

**Query Example:**
```php
$category = Category::find(1);
$products = $category->products; // Get all products in category
```

#### Category → Subcategories
```
One category can have many child categories
categories.id → categories.parent_id
```

**Query Example:**
```php
$category = Category::find(1);
$children = $category->children; // Get subcategories
```

#### Product → Inventory History
```
One product can have many inventory history entries
products.id → inventory_history.product_id
```

#### Order → Order Items
```
One order can have many items
orders.id → order_items.order_id
```

#### Order → Coupon
```
One order can optionally have one coupon
orders.coupon_id → coupons.id
```

#### Order → Return Request
```
One order can have one return request
orders.id → return_requests.order_id
```

#### User → Return Requests
```
One user can have many return requests
users.id → return_requests.user_id
```

### Many-to-Many Relationships

#### Orders → Products (through order_items)
```
Orders have many products
Products are in many orders
```

**Query Example:**
```php
$order = Order::with('items.product')->find(1);
foreach ($order->items as $item) {
    echo $item->product->name;
}
```

## Indexes

### Performance Indexes

```
Table              Column              Purpose
─────────────────────────────────────────────────────────────
users              role                Filter by role
categories         parent_id           Filter by parent
products           category_id         Filter by category
orders             user_id             Get user's orders
orders             status              Filter by status
order_items        order_id            Get order items
order_items        product_id          Get product orders
inventory_history  product_id          Get product history
inventory_history  change_type         Filter by change type
coupons            code (unique)       Look up coupon by code
orders             coupon_id           Get orders using a coupon
return_requests    status              Filter by status
```

### Query Performance Tips

1. **Use indexes for WHERE clauses**
   ```sql
   -- Fast (indexed)
   SELECT * FROM products WHERE category_id = 1;
   
   -- Slower (no index)
   SELECT * FROM products WHERE description LIKE '%laptop%';
   ```

2. **Join on indexed columns**
   ```sql
   -- Fast (category_id indexed)
   SELECT p.* FROM products p
   JOIN categories c ON p.category_id = c.id
   WHERE c.id = 1;
   ```

3. **Use EXPLAIN to analyze queries**
   ```sql
   EXPLAIN QUERY PLAN
   SELECT * FROM products WHERE price > 100 AND category_id = 1;
   ```

---

## Migrations

### Migration Files

```
database/migrations/
├── 0001_01_01_000000_create_users_table.php          (users, password_reset_tokens, sessions)
├── 0001_01_01_000001_create_cache_table.php           (cache, cache_locks)
├── 0001_01_01_000002_create_jobs_table.php             (jobs, job_batches, failed_jobs)
├── 2025_12_14_170736_add_profile_fields_to_users_table.php  (role, address, phone)
├── 2025_12_14_171040_create_categories_table.php
├── 2025_12_14_171041_create_orders_table.php
├── 2025_12_14_171042_create_products_table.php
├── 2025_12_14_171044_create_order_items_table.php
├── 2025_12_14_171045_create_inventory_history_table.php
├── 2026_03_04_000001_drop_cart_tables.php              (removes carts and cart_items)
├── 2026_03_06_000001_add_fulltext_index_to_products_table.php  (fulltext search on products)
├── 2026_03_06_000002_create_return_requests_table.php  (return/refund requests)
├── 2026_03_06_000003_create_coupons_table.php          (discount coupons)
└── 2026_03_06_000004_add_coupon_fields_to_orders_table.php  (coupon_id, discount_amount on orders)
```

**Note:** The cart feature was removed. The `2026_03_04_000001_drop_cart_tables.php` migration drops the `carts` and `cart_items` tables that were created by earlier migrations.

### Running Migrations

```bash
# Run all pending migrations
php artisan migrate

# Rollback last migration batch
php artisan migrate:rollback

# Reset all migrations
php artisan migrate:reset

# Refresh (reset + migrate)
php artisan migrate:refresh

# Refresh with seeds
php artisan migrate:refresh --seed

# Check migration status
php artisan migrate:status
```

### Creating New Migration

```bash
# Create migration for new table
php artisan make:migration create_new_table

# Create migration to modify existing table
php artisan make:migration add_column_to_products

# Create migration in custom location
php artisan make:migration create_new_table --path=database/migrations/custom
```

---

## Database Queries Examples

### Product Queries

```php
// Get all products in a category
$products = Product::where('category_id', 1)->get();

// Get expensive products
$products = Product::where('price', '>', 500)->get();

// Get low stock products
$products = Product::where('quantity', '<', 10)->get();

// Search products by name
$products = Product::where('name', 'like', '%laptop%')->get();

// Get products with category
$products = Product::with('category')->get();

// Get category with all products
$category = Category::with('products')->find(1);

// Get product with inventory history
$product = Product::with('inventoryHistory')->find(1);
```

### Order Queries

```php
// Get user's orders
$orders = Order::where('user_id', 1)->get();

// Get pending orders
$orders = Order::where('status', 'pending')->get();

// Get order with items and products
$order = Order::with('items.product')->find(1);

// Get recent orders
$orders = Order::orderBy('created_at', 'desc')->get();

// Get orders over $100
$orders = Order::where('total_price', '>', 100)->get();
```

### Category Queries

```php
// Get root categories
$categories = Category::whereNull('parent_id')->get();

// Get subcategories
$categories = Category::where('parent_id', 1)->get();

// Get category with children
$category = Category::with('children')->find(1);

// Get all descendants (recursive)
$descendants = $category->descendants();
```

---

This schema is designed for:
- ✅ Flexibility (hierarchical categories)
- ✅ Auditability (inventory history)
- ✅ Performance (strategic indexes)
- ✅ Data integrity (foreign keys, constraints)
- ✅ Scalability (proper normalization)

