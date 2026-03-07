# Shop API - Setup & Development Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Local Installation](#local-installation)
3. [Docker Development Setup](#docker-development-setup)
4. [Development Workflow](#development-workflow)
5. [Testing](#testing)
6. [Database Management](#database-management)
7. [Debugging](#debugging)
8. [Code Standards](#code-standards)
9. [Environment Configuration](#environment-configuration)
10. [Deployment Checklist](#deployment-checklist)

---

## Prerequisites

- **PHP 8.2+**, **Composer 2.x**, **Node.js 18+**, **npm 9+**
- **Docker** (optional — for containerised setup)

Verify:
```bash
php --version && composer --version && node --version && npm --version
```

---

## Local Installation

> For a quick copy-paste start, see the [README](../README.md#-quick-start).

```bash
git clone <repository-url> && cd shop-api
composer install          # also installs pre-commit git hooks
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed       # optional
npm install && npm run build
php artisan serve
```

The API is now available at `http://localhost:8000/api/v1/`

---

## Docker Development Setup

The project includes a full Docker setup with PHP-FPM, Nginx, PostgreSQL 16, and Redis.

### 1. Start All Services

```bash
docker compose up -d --build
```

This starts:
- **app** — PHP 8.3-FPM (dev target)
- **queue** — Laravel queue worker for background jobs
- **nginx** — Reverse proxy on port 8081
- **postgres** — PostgreSQL 16 database
- **redis** — Redis 7 for caching and queues

### 2. Install Dependencies & Setup

```bash
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 3. Access the API

The API is available at `http://localhost:8081/api/v1/`

### 4. Running Commands Inside the Container

```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```

### 5. Viewing Logs

```bash
docker compose logs -f app
docker compose logs -f queue
```

### 6. Stopping Services

```bash
docker compose down
```

### Environment Variables

Database credentials are configured via environment variables with defaults:

| Variable            | Default | Description        |
|---------------------|---------|--------------------|
| `POSTGRES_DB`       | `shop`  | Database name      |
| `POSTGRES_USER`     | `shop`  | Database user      |
| `POSTGRES_PASSWORD` | `secret`| Database password  |

Override them by creating a `.env` file in the project root or exporting them in your shell.

### Production Deployment

Use the production compose override to build optimised images with no volume mounts and no exposed database/redis ports:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

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

### Run E2E Tests Only
```bash
php artisan test --testsuite=E2E
```

E2E tests simulate full user scenarios that span multiple API calls, such as browsing products, placing orders, processing payments, and shipping. They live in `tests/E2E/`.

### Run Performance Tests Only
```bash
php artisan test --testsuite=Performance
```

Performance tests verify that critical API endpoints respond within acceptable time thresholds and handle large datasets efficiently. They live in `tests/Performance/`.

### Run Security Tests Only
```bash
php artisan test --testsuite=Security
```

Security tests check for common vulnerabilities including SQL injection, XSS, authentication/authorization bypass, webhook signature verification, input validation, mass assignment, order state manipulation, and data exposure. They live in `tests/Security/`.

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

## Troubleshooting

**"Connection refused"** → ensure `php artisan serve` is running; check `lsof -i :8000`.

**"Database locked"** → `php artisan cache:clear` then `php artisan migrate:refresh`.

**"Class not found"** → `composer dump-autoload` and `php artisan ide-helper:generate`.

**Tests failing** → `php artisan migrate --env=testing` then `php artisan test`.

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
CACHE_STORE=array

# Queue
QUEUE_CONNECTION=sync

# Webhooks (optional, leave unset to disable)
WEBHOOK_ORDER_PAID_URL=
WEBHOOK_SIGNING_SECRET=

# Mail
MAIL_MAILER=log
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
CACHE_STORE=redis
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
- [ ] Run tests: `php artisan test`
- [ ] Set proper file permissions
- [ ] Set up error logging
- [ ] Set up database backups

---


