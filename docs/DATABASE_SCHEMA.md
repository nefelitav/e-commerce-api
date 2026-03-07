# Shop API - Database Schema Documentation

## Table of Contents
1. [Overview](#overview)
2. [Tables](#tables)
3. [Relationships](#relationships)

> **Visual ERD** → see [UML_DIAGRAMS.md](./UML_DIAGRAMS.md#entity-relationship-diagram)
> **Migration commands** → see [SETUP_AND_DEVELOPMENT.md](./SETUP_AND_DEVELOPMENT.md#database-management)

---

## Overview

The Shop API uses a relational database with the following core tables:

- **users** — User/customer accounts
- **categories** — Product categories (hierarchical)
- **products** — Product catalog
- **orders** — Customer orders
- **order_items** — Items within orders
- **inventory_history** — Inventory change tracking
- **coupons** — Discount coupons
- **return_requests** — Return/refund requests

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

**Status Values:**
- `pending` - Order created, awaiting payment
- `payment_failed` - Payment attempt failed, can retry
- `paid` - Payment received
- `processing` - Warehouse preparing the package for shipment
- `shipped` - Order picked up by shipping carrier
- `delivered` - Order delivered to customer
- `cancelled` - Order cancelled (auto-refund if previously paid/processing)
- `refunded` - Order refunded (stock restored)

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

**Status Values (App\Enums\ReturnRequestStatus):**
- `pending` - Request submitted, awaiting admin review
- `approved` - Return approved, order refunded, stock restored
- `returning` - Return approved, awaiting item to be received back
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

---

