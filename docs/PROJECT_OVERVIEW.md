# Shop API - Project Overview

## 📋 Table of Contents
1. [Project Description](#project-description)
2. [Technology Stack](#technology-stack)
3. [Project Goals](#project-goals)
4. [Architecture Overview](#architecture-overview)
5. [Key Features](#key-features)
6. [File Structure](#file-structure)
7. [Getting Started](#getting-started)

---

## Project Description

**Shop API** is a RESTful API built with Laravel 12 that provides a comprehensive e-commerce backend system. It manages products, categories, orders, carts, and inventory with advanced filtering, sorting, and pagination capabilities.

### Primary Purpose
- Provide a robust backend for e-commerce applications
- Handle product catalog management
- Process orders and maintain inventory
- Manage shopping carts and user interactions
- Support complex queries with filtering and sorting

### Target Users
- Frontend developers integrating with the API
- Mobile app developers
- Third-party integrations
- Admin dashboard applications

---

## Technology Stack

### Backend Framework
- **Laravel 12.0** - PHP web framework
- **PHP 8.2+** - Programming language

### Database
- **SQLite** (Development)
- **Eloquent ORM** - Database abstraction layer
- **Migrations** - Database versioning

### API Documentation
- **L5 Swagger** - Swagger/OpenAPI documentation

### Development Tools
- **PHPUnit 11.5** - Testing framework
- **Mockery 1.6** - Mock objects
- **Laravel Pint** - PHP code style fixer
- **PHPStan** - Static analysis
- **Larastan** - Laravel PHPStan integration

### Code Quality
- **PHPStan** - Static type analysis
- **Laravel IDE Helper** - IDE autocompletion
- **PHP CS Fixer** - Code formatting

---

## Project Goals

### Short-term Goals (Completed ✅)
- ✅ Create RESTful API endpoints for core resources
- ✅ Implement CRUD operations for all entities
- ✅ Add comprehensive filtering capabilities
- ✅ Add sorting and pagination
- ✅ Implement request validation
- ✅ Create response standardization
- ✅ Add error handling

### Medium-term Goals
- Implement authentication and authorization
- Add advanced filtering (AND/OR logic)
- Create audit logging
- Add caching layer
- Implement webhooks

### Long-term Goals
- Multi-tenant support
- Advanced analytics
- Performance optimization
- Mobile app support
- Third-party integrations

---

## Architecture Overview

### Design Patterns Used

#### 1. **Repository Pattern**
- Abstracts database access
- Provides consistent interface for data operations
- Located in: `app/Repositories/`

#### 2. **Service Layer Pattern**
- Contains business logic
- Orchestrates repository operations
- Located in: `app/Services/`

#### 3. **Data Transfer Objects (DTOs)**
- Encapsulate data structures
- Provide type safety
- Located in: `app/Dto/`

#### 4. **Request/Response Pattern**
- Validates input (Requests)
- Standardizes output (Responses)
- Located in: `app/Http/Requests/` and `app/Http/Responses/`

#### 5. **Transformer Pattern**
- Converts DTOs to API response format
- Located in: `app/Transformers/`

### Layered Architecture

```
┌─────────────────────────────┐
│    HTTP Layer               │
│  (Controllers & Routes)     │
├─────────────────────────────┤
│    Request Validation       │
│  (Form Requests)            │
├─────────────────────────────┤
│    Business Logic           │
│  (Services)                 │
├─────────────────────────────┤
│    Data Access              │
│  (Repositories)             │
├─────────────────────────────┤
│    Database Layer           │
│  (Eloquent Models)          │
├─────────────────────────────┤
│    Response Formatting      │
│  (Transformers)             │
└─────────────────────────────┘
```

---

## Key Features

### 1. **Product Management**
- List products with filtering (name, category, price range, quantity range)
- Sort by any field (id, name, price, quantity, created_at, updated_at)
- Paginated results with metadata
- Include related categories

### 2. **Category Management**
- Hierarchical categories (parent-child relationships)
- Filter by name or parent category
- List subcategories
- Full pagination support

### 3. **Order Management**
- Create and manage orders
- Filter by status or price range
- Track order items
- Pagination support

### 4. **Cart Management**
- User-specific shopping carts
- Add/remove items from cart
- Sorted by creation date
- Include cart items

### 5. **Inventory Management**
- Track product quantities
- Maintain inventory history
- Update inventory on order

### 6. **Advanced Querying**
- Multi-field filtering with AND logic
- Sort by any relevant field
- Pagination with configurable page size
- Load related resources via includes

### 7. **Standardized Responses**
- Consistent JSON format
- Pagination metadata
- Success/error indicators
- Helpful messages

### 8. **Request Validation**
- Validate all input parameters
- Type checking (integer, string, numeric)
- Range validation
- Database existence validation

---

## File Structure

### Core Application

```
app/
├── Dto/                          # Data Transfer Objects
│   ├── Cart/
│   ├── Category/
│   ├── InventoryHistory/
│   ├── Order/
│   └── Product/
├── Exceptions/                   # Custom Exceptions
│   ├── BadRequestException.php
│   ├── CartNotFoundException.php
│   ├── CategoryAlreadyExistsException.php
│   ├── CategoryNotFoundException.php
│   ├── OrderNotFoundException.php
│   ├── ProductAlreadyExistsException.php
│   ├── ProductNotFoundException.php
│   └── UnprocessableEntityException.php
├── Http/
│   ├── Controllers/              # API Controllers
│   │   └── Api/V1/
│   │       ├── Cart/
│   │       ├── Category/
│   │       ├── InventoryHistory/
│   │       ├── Order/
│   │       └── Product/
│   ├── Requests/                 # Form Requests (Validation)
│   │   ├── Cart/
│   │   ├── Category/
│   │   ├── Order/
│   │   └── Product/
│   └── Responses/                # Response Objects
│       ├── Cart/
│       ├── Category/
│       ├── Order/
│       └── Product/
├── Models/                       # Eloquent Models
│   ├── CreatedAtUtcTrait.php
│   ├── UpdatedAtUtcTrait.php
│   ├── UserModel.php
│   ├── Cart/
│   ├── Category/
│   ├── InventoryHistory/
│   ├── Order/
│   └── Product/
├── Repositories/                 # Data Access Layer
│   ├── Cart/
│   ├── Category/
│   ├── InventoryHistory/
│   ├── Order/
│   └── Product/
├── Services/                     # Business Logic Layer
│   ├── Cart/
│   ├── Category/
│   ├── InventoryHistory/
│   ├── Order/
│   └── Product/
├── Transformers/                 # Response Transformation
│   ├── CartTransformer.php
│   ├── CategoryTransformer.php
│   ├── InventoryHistoryTransformer.php
│   ├── OrderTransformer.php
│   └── ProductTransformer.php
└── Providers/
    └── AppServiceProvider.php

routes/
├── api.php                       # API Routes
├── console.php
└── web.php

tests/
├── Feature/                      # Feature Tests
│   └── Controllers/
├── Unit/                         # Unit Tests
│   └── Services/
└── TestCase.php

database/
├── migrations/                   # Database Migrations
├── seeders/
└── factories/                    # Model Factories

config/                           # Configuration Files
├── app.php
├── auth.php
├── cache.php
├── database.php
├── l5-swagger.php
└── ...

storage/
├── api-docs/                     # Generated Swagger Docs
├── app/
├── framework/
└── logs/
```

---

## Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and npm
- SQLite (included)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd shop-api
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup database**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Install Node dependencies**
   ```bash
   npm install
   npm run build
   ```

6. **Generate API documentation**
   ```bash
   php artisan l5-swagger:generate
   ```

7. **Start development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000/api/v1/`

---

## Project Statistics

- **Controllers**: 20+ (CRUD operations for 5 resources)
- **Models**: 5 core entities (Product, Category, Order, Cart, InventoryHistory)
- **Repositories**: 5 (Data access layer)
- **Services**: 5 (Business logic layer)
- **Tests**: 20+ (Feature and Unit tests)
- **API Endpoints**: 25+ RESTful endpoints
- **Lines of Code**: 3000+

---

## Next Steps

For detailed documentation, see:
- [API DOCUMENTATION](./API_DOCUMENTATION.md) - Full API endpoint reference
- [SETUP_GUIDE.md](./SETUP_GUIDE.md) - Detailed setup instructions
- [ARCHITECTURE.md](./ARCHITECTURE.md) - Detailed architecture guide
- [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) - Database structure
- [DEVELOPMENT_GUIDE.md](./DEVELOPMENT_GUIDE.md) - Development guidelines

