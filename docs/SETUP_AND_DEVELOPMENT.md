# Shop API - Setup & Development Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Development Workflow](#development-workflow)
4. [Testing](#testing)
5. [Database Management](#database-management)
6. [Debugging](#debugging)
7. [Code Standards](#code-standards)
8. [Common Tasks](#common-tasks)

---

## Prerequisites

### Required
- **PHP 8.2+** - Get from [php.net](https://www.php.net/downloads)
- **Composer** - Get from [getcomposer.org](https://getcomposer.org)
- **Node.js 18+** - Get from [nodejs.org](https://nodejs.org)
- **Git** - Get from [git-scm.com](https://git-scm.com)

### Optional
- **SQLite UI** - [DB Browser for SQLite](https://sqlitebrowser.org/)
- **API Client** - [Postman](https://www.postman.com/) or [Insomnia](https://insomnia.rest/)
- **Code Editor** - [VS Code](https://code.visualstudio.com/) with Laravel extensions

### Verify Installation
```bash
php --version      # Should be 8.2+
composer --version # Should be 2.x
node --version     # Should be 18+
npm --version      # Should be 9+
```

---

## Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd shop-api
```

### 2. Install PHP Dependencies
```bash
composer install
```

> **Note:** This also installs git hooks automatically (pre-commit hook for code style and static analysis).

### 3. Copy Environment Configuration
```bash
cp .env.example .env
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Create SQLite Database
```bash
touch database/database.sqlite
```

### 6. Run Migrations
```bash
php artisan migrate
```

### 7. Seed Database (Optional)
```bash
php artisan db:seed
```

### 8. Install Node Dependencies
```bash
npm install
```

### 9. Build Assets
```bash
npm run build
```

### 10. Generate API Documentation
```bash
php artisan l5-swagger:generate
```

### 11. Start Development Server
```bash
php artisan serve
```

The API is now available at `http://localhost:8000/api/v1/`

---

## Development Workflow

### Daily Development Setup

```bash
# 1. Update to latest code
git pull origin main

# 2. Install any new dependencies
composer install

# 3. Run migrations (if new ones added)
php artisan migrate

# 4. Start development server
php artisan serve

# 5. In another terminal, start Vite (for frontend)
npm run dev
```

### Feature Development Process

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/my-feature
   ```

2. **Create Migration (if needed)**
   ```bash
   php artisan make:migration create_resources_table
   ```

3. **Create Model**
   ```bash
   php artisan make:model Models/Resource/ResourceModel -m
   ```

4. **Create Repository**
   ```bash
   # Manually create: app/Repositories/Resource/ResourceRepository.php
   ```

5. **Create Service**
   ```bash
   # Manually create: app/Services/Resource/ResourceService.php
   ```

6. **Create Controllers**
   ```bash
   php artisan make:controller Api/V1/Resource/ListResourcesController --api
   ```

7. **Create Requests**
   ```bash
   php artisan make:request Resource/ListResourcesRequest
   ```

8. **Create Tests**
   ```bash
   php artisan make:test Feature/Controllers/Resource/ListResourcesControllerTest
   ```

9. **Run Tests**
   ```bash
   php artisan test
   ```

10. **Commit Changes**
    ```bash
    git add .
    git commit -m "feat: Add resource listing"
    git push origin feature/my-feature
    ```

---

## Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Class
```bash
php artisan test tests/Feature/Controllers/Product/ListProductsControllerTest.php
```

### Run Specific Test Method
```bash
php artisan test tests/Feature/Controllers/Product/ListProductsControllerTest.php --filter test_index_returns_list_of_products
```

### Run Unit Tests Only
```bash
php artisan test tests/Unit
```

### Run Feature Tests Only
```bash
php artisan test tests/Feature
```

### Run Tests with Coverage
```bash
php artisan test --coverage
```

### Create New Test
```bash
# Feature test
php artisan make:test Feature/Controllers/Product/CreateProductControllerTest

# Unit test
php artisan make:test Unit/Services/ProductServiceTest --unit
```

### Test Structure

**Feature Test Example:**
```php
namespace Tests\Feature\Controllers\Product;

use App\Models\Product\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_products(): void
    {
        // Arrange
        ProductModel::factory()->count(3)->create();

        // Act
        $response = $this->getJson(route('v1.products.index'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'price']
                ],
                'meta'
            ]);
    }
}
```

**Unit Test Example:**
```php
namespace Tests\Unit\Services;

use App\Services\Product\ProductService;
use App\Repositories\Product\ProductRepository;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    private ProductService $service;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->createMock(ProductRepository::class);
        $this->service = new ProductService($this->repository);
    }

    public function test_list_products_calls_repository(): void
    {
        // Arrange
        $this->repository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn(collect([]));

        // Act
        $this->service->listProducts();

        // Assert - verified through mock expectations
    }
}
```

---

## Database Management

### Migrations

**Create Migration**
```bash
php artisan make:migration create_products_table
```

**Run Migrations**
```bash
php artisan migrate
```

**Rollback Last Migration**
```bash
php artisan migrate:rollback
```

**Rollback All Migrations**
```bash
php artisan migrate:reset
```

**Refresh Database (Reset + Migrate)**
```bash
php artisan migrate:refresh
```

**Refresh with Seeds**
```bash
php artisan migrate:refresh --seed
```

### Seeds

**Create Seeder**
```bash
php artisan make:seeder ProductSeeder
```

**Run Seeds**
```bash
php artisan db:seed
```

**Run Specific Seeder**
```bash
php artisan db:seed --class=ProductSeeder
```

### Database Browser

**View Database with SQLite Browser**
```bash
# Mac
open database/database.sqlite

# Linux
sqlite3 database/database.sqlite

# Windows
Start database/database.sqlite
```

**Query Database via CLI**
```bash
sqlite3 database/database.sqlite "SELECT * FROM products LIMIT 10;"
```

---

## Debugging

### Laravel Tinker (REPL)
```bash
php artisan tinker

# Query examples
>>> App\Models\Product\ProductModel::all();
>>> App\Models\Product\ProductModel::find(1);
>>> App\Models\Category\CategoryModel::with('products')->get();
```

### Laravel Pail (Log Viewer)
```bash
# In a separate terminal
php artisan pail
```

### Debug with dd()
```php
// In controller, service, etc.
dd($variable);  // Dies and dumps variable

dump($variable); // Dumps variable and continues
```

### Check Routes
```bash
php artisan route:list

# Filter routes
php artisan route:list --name=products

# Show specific route
php artisan route:list --path=api/v1/products
```

### Cache Commands
```bash
# Clear all cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear
```

---

## Code Standards

### Git Hooks (Pre-commit)

The project uses a **pre-commit git hook** that runs automatically before each commit:

1. **Laravel Pint** — fixes code style and removes unused imports on staged PHP files
2. **PHPStan** — runs static analysis on staged PHP files

Fixed files are automatically re-staged. If PHPStan finds errors, the commit is aborted.

**Install hooks** (automatically done on `composer install`):
```bash
composer run setup-hooks
```

### PHP Code Style (Laravel Pint)

Pint is configured via `pint.json` with the `laravel` preset plus additional rules for unused import removal, import ordering, and trailing commas.

```bash
# Fix all code style issues
composer run fix

# Check code style without fixing (dry-run)
composer run lint
```

### Static Analysis (PHPStan)

PHPStan is configured via `phpstan.neon` at level 7 with Larastan extensions.

```bash
# Run PHPStan analysis
composer run analyse
```

### Run All Checks

```bash
# Run Pint (dry-run) + PHPStan together
composer run check
```

### PHP CS Fixer (Legacy)

PHP CS Fixer is also available if needed:
```bash
./vendor/bin/php-cs-fixer fix app
```

### Laravel IDE Helper

**Generate IDE Helper**
```bash
php artisan ide-helper:generate
```

**Generate Model Info**
```bash
php artisan ide-helper:models
```

### Code Review Checklist

- [ ] Code follows PSR-12 standards
- [ ] Variable names are descriptive
- [ ] Methods are focused and single-responsibility
- [ ] Error handling is appropriate
- [ ] Input validation is complete
- [ ] Tests are included
- [ ] Documentation is updated
- [ ] No debug code (dd, dump, etc.)
- [ ] Dependencies are injected
- [ ] Exceptions are custom/specific

---

## Common Tasks

### Add New Product

**Via API:**
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Product",
    "description": "Product description",
    "price": 99.99,
    "quantity": 10,
    "category_id": 1
  }'
```

**Via Tinker:**
```bash
php artisan tinker
>>> $product = App\Models\Product\ProductModel::create([
    'name' => 'New Product',
    'description' => 'Description',
    'price' => 99.99,
    'quantity' => 10,
    'category_id' => 1
]);
```

### Reset Database

```bash
php artisan migrate:refresh --seed
```

### Export Database

```bash
sqlite3 database/database.sqlite .dump > database_backup.sql
```

### Import Database

```bash
sqlite3 database/database.sqlite < database_backup.sql
```

### Generate API Docs

```bash
php artisan l5-swagger:generate
```

### Access Swagger UI

```
http://localhost:8000/api/documentation
```

### Monitor Requests

```bash
# Terminal 1: Start server
php artisan serve

# Terminal 2: Monitor logs
php artisan pail

# Terminal 3: Run tests
php artisan test
```

### Troubleshooting

**"Connection refused" error**
```bash
# Make sure server is running
php artisan serve

# Check port 8000 is not in use
lsof -i :8000
```

**"Database locked" error**
```bash
# Clear all cache
php artisan cache:clear

# Reset database
php artisan migrate:refresh
```

**"Class not found" error**
```bash
# Regenerate autoloader
composer dump-autoload

# Generate IDE helper
php artisan ide-helper:generate
```

**Tests failing**
```bash
# Make sure database is set up
php artisan migrate --env=testing

# Run tests
php artisan test
```

---

## Performance Tips

### Optimization

1. **Add Indexes**
   ```php
   // In migration
   Schema::table('products', function (Blueprint $table) {
       $table->index('category_id');
       $table->index('price');
   });
   ```

2. **Use Select Carefully**
   ```php
   // Don't select all columns if not needed
   ProductModel::select('id', 'name', 'price')->get();
   ```

3. **Eager Load Relationships**
   ```php
   // Good
   ProductModel::with('category')->get();
   
   // Avoid
   $products = ProductModel::all();
   foreach ($products as $product) {
       echo $product->category->name; // N+1 query
   }
   ```

4. **Cache Results**
   ```php
   $products = cache()->remember('products', now()->addHours(1), function () {
       return ProductModel::all();
   });
   ```

### Monitoring

```bash
# Check query count
php artisan pail

# Monitor database
sqlite3 database/database.sqlite ".open"

# Profile specific request
php artisan tinker
>>> App\Models\Product\ProductModel::enableQueryLog();
>>> // Run your code
>>> dd(DB::getQueryLog());
```

---

## Environment Configuration

### Key `.env` Variables

```bash
# App
APP_NAME="Shop API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Cache
CACHE_DRIVER=file

# Queue
QUEUE_CONNECTION=sync

# Webhooks (optional, leave unset to disable)
WEBHOOK_ORDER_PAID_URL=
WEBHOOK_SIGNING_SECRET=

# Mail
MAIL_DRIVER=log
```

### Common Configuration Changes

**Enable Debug Mode**
```bash
APP_DEBUG=true
```

**Change Database**
```bash
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=shop_api
DB_USERNAME=root
DB_PASSWORD=
```

**Enable Caching**
```bash
CACHE_DRIVER=redis
```

---

## Deployment Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Run `composer install --no-dev`
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Cache configuration: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Generate API docs: `php artisan l5-swagger:generate`
- [ ] Run tests: `php artisan test`
- [ ] Set proper file permissions
- [ ] Set up error logging
- [ ] Set up database backups

---

This guide covers all aspects of development. For API-specific details, see [API_DOCUMENTATION.md](./API_DOCUMENTATION.md). For architecture details, see [ARCHITECTURE.md](./ARCHITECTURE.md).

