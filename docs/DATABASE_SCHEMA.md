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

The Shop API uses a relational database design with 5 core tables:

- **users** - User/customer accounts
- **categories** - Product categories (hierarchical)
- **products** - Product catalog
- **orders** - Customer orders
- **order_items** - Items within orders
- **carts** - Shopping carts
- **cart_items** - Items in carts
- **inventory_history** - Inventory change tracking

---

## Entity Relationship Diagram

```
┌─────────────┐
│   users     │
├─────────────┤
│ id (PK)     │
│ name        │
│ email       │
│ created_at  │
└──────┬──────┘
       │
       │ 1:M
       │
   ┌───┴─────────┐
   │             │
   ▼             ▼
┌─────────┐  ┌──────────┐
│ orders  │  │  carts   │
└────┬────┘  └────┬─────┘
     │            │
     │            │ 1:M
     │            │
     ▼            ▼
┌──────────┐  ┌──────────┐
│order_    │  │cart_     │
│items     │  │items     │
└────┬─────┘  └────┬─────┘
     │ M:1        │ M:1
     │            │
     └────┬───────┘
          │
          ▼
    ┌──────────────┐
    │  products    │
    ├──────────────┤
    │ id (PK)      │
    │ category_id  │
    │ name         │
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
    └──────────────┘

    ┌─────────────────────┐
    │ inventory_history   │
    ├─────────────────────┤
    │ id (PK)             │
    │ product_id (FK)     │
    │ quantity_change     │
    │ reason              │
    │ created_at          │
    └─────────────────────┘
```

---

## Tables

### users Table

**Purpose:** Store user/customer information

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       User ID
name           string        NOT NULL           User name
email          string        UNIQUE, NOT NULL   Email address
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

### categories Table

**Purpose:** Store product categories (supports hierarchical structure)

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Category ID
parent_id      bigint        FK, Nullable       Parent category
name           string        NOT NULL, UNIQUE   Category name
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE categories (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    parent_id BIGINT REFERENCES categories(id),
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_categories_parent_id ON categories(parent_id);
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
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Product ID
category_id    bigint        FK, NOT NULL       Category reference
name           string        NOT NULL, UNIQUE   Product name
description    text          NOT NULL           Product description
price          decimal(8,2)  NOT NULL, ≥ 0      Product price
quantity       integer       NOT NULL, ≥ 0      Stock quantity
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    category_id BIGINT NOT NULL REFERENCES categories(id),
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price DECIMAL(8, 2) NOT NULL,
    quantity INTEGER NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CHECK (price >= 0),
    CHECK (quantity >= 0)
);

CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_price ON products(price);
CREATE INDEX idx_products_quantity ON products(quantity);
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
total_price    decimal(10,2) NOT NULL, ≥ 0      Total order price
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
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CHECK (total_price >= 0)
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
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Item ID
order_id       bigint        FK, NOT NULL       Order reference
product_id     bigint        FK, NOT NULL       Product reference
quantity       integer       NOT NULL, > 0      Quantity ordered
price          decimal(8,2)  NOT NULL, > 0      Price at time of order
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE order_items (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    order_id BIGINT NOT NULL REFERENCES orders(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    price DECIMAL(8, 2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CHECK (quantity > 0),
    CHECK (price > 0)
);

CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
```

---

### carts Table

**Purpose:** Store shopping carts for users

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Cart ID
user_id        bigint        FK, NOT NULL       User reference
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE carts (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    user_id BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_carts_user_id ON carts(user_id);
```

---

### cart_items Table

**Purpose:** Store individual items in shopping carts

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       Item ID
cart_id        bigint        FK, NOT NULL       Cart reference
product_id     bigint        FK, NOT NULL       Product reference
quantity       integer       NOT NULL, > 0      Quantity in cart
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE cart_items (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    cart_id BIGINT NOT NULL REFERENCES carts(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CHECK (quantity > 0)
);

CREATE INDEX idx_cart_items_cart_id ON cart_items(cart_id);
CREATE INDEX idx_cart_items_product_id ON cart_items(product_id);
```

---

### inventory_history Table

**Purpose:** Track inventory changes for audit and analytics

**Structure:**
```
Column Name    Type          Constraints        Description
────────────────────────────────────────────────────────────
id             bigint        PK, Auto-inc       History ID
product_id     bigint        FK, NOT NULL       Product reference
quantity_change integer      NOT NULL           Change amount
reason         string        NOT NULL           Change reason
created_at     timestamp     DEFAULT NULL       Creation timestamp
updated_at     timestamp     DEFAULT NULL       Last update timestamp
```

**SQL:**
```sql
CREATE TABLE inventory_history (
    id BIGINT PRIMARY KEY AUTOINCREMENT,
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity_change INTEGER NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_inventory_history_product_id ON inventory_history(product_id);
```

**Reason Examples:**
- `initial` - Initial inventory
- `purchase` - Purchased from supplier
- `sale` - Sold to customer
- `return` - Customer return
- `adjustment` - Inventory adjustment
- `damage` - Damaged goods

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

#### User → Carts
```
One user can have many carts
users.id → carts.user_id
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

#### Cart → Cart Items
```
One cart can have many items
carts.id → cart_items.cart_id
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

#### Carts → Products (through cart_items)
```
Carts have many products
Products are in many carts
```

---

## Indexes

### Performance Indexes

```
Table            Column              Purpose
───────────────────────────────────────────────────────────
categories       parent_id           Filter by parent
products         category_id         Filter by category
products         price               Sort/filter by price
products         quantity            Filter by quantity
orders           user_id             Get user's orders
orders           status              Filter by status
order_items      order_id            Get order items
order_items      product_id          Get product orders
carts            user_id             Get user's carts
cart_items       cart_id             Get cart items
cart_items       product_id          Get product carts
inventory_history product_id         Get product history
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
├── 2024_01_15_000000_create_users_table.php
├── 2024_01_15_000001_create_categories_table.php
├── 2024_01_15_000002_create_products_table.php
├── 2024_01_15_000003_create_orders_table.php
├── 2024_01_15_000004_create_order_items_table.php
├── 2024_01_15_000005_create_carts_table.php
├── 2024_01_15_000006_create_cart_items_table.php
└── 2024_01_15_000007_create_inventory_history_table.php
```

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

### Cart Queries

```php
// Get user's cart
$cart = Cart::where('user_id', 1)->first();

// Get cart with items
$cart = Cart::with('items.product')->find(1);

// Get items in cart
$items = CartItem::where('cart_id', 1)->get();
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

