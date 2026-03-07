# Shop API - Setup & Development Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Local Installation](#local-installation)
3. [Docker Setup](#docker-setup)
4. [Testing](#testing)
5. [Database Management](#database-management)
6. [Debugging](#debugging)
7. [Code Standards](#code-standards)
8. [Environment Configuration](#environment-configuration)
9. [Deployment Checklist](#deployment-checklist)

---

## Prerequisites

- **PHP 8.2+**, **Composer 2.x**, **Node.js 18+**, **npm 9+**
- **Docker** (optional — for containerised setup)

---

## Local Installation

> Quick copy-paste start → [README](../README.md#-quick-start)

```bash
git clone <repository-url> && cd shop-api
composer install          # also installs pre-commit git hooks
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed       # optional
npm install && npm run build
php artisan serve         # → http://localhost:8000/api/v1/
```

---

## Docker Setup

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API available at `http://localhost:8081/api/v1/`

**Services:** app (PHP-FPM), queue (Laravel worker), nginx (:8081), postgres (16), redis (7)

```bash
docker compose exec app php artisan <command>   # run commands
docker compose logs -f app                      # view logs
docker compose down                             # stop
```

| Variable | Default | Description |
|----------|---------|-------------|
| `POSTGRES_DB` | `shop` | Database name |
| `POSTGRES_USER` | `shop` | Database user |
| `POSTGRES_PASSWORD` | `secret` | Database password |

**Production:**
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

---

## Testing

```bash
php artisan test                                    # all tests
php artisan test tests/Unit                         # unit only
php artisan test tests/Feature                      # feature only
php artisan test --testsuite=E2E                    # end-to-end scenarios
php artisan test --testsuite=Performance            # throughput & latency
php artisan test --testsuite=Security               # vulnerability checks
php artisan test --coverage                         # with coverage
php artisan test --filter test_method_name          # single method
```

Tests live in `tests/{Unit,Feature,E2E,Performance,Security}/`.

```bash
# Create new tests
php artisan make:test Feature/Controllers/Product/CreateProductControllerTest
php artisan make:test Unit/Services/ProductServiceTest --unit
```

---

## Database Management

```bash
php artisan migrate                      # run pending migrations
php artisan migrate:rollback             # rollback last batch
php artisan migrate:refresh --seed       # reset + migrate + seed
php artisan db:seed                      # run seeders
php artisan db:seed --class=ProductSeeder # specific seeder
php artisan make:migration create_x_table # new migration
```

**Query via CLI:**
```bash
sqlite3 database/database.sqlite "SELECT * FROM products LIMIT 10;"
```

---

## Debugging

```bash
php artisan tinker           # REPL — query models interactively
php artisan pail             # live log viewer (separate terminal)
php artisan route:list       # list all registered routes
```

**Clear caches:**
```bash
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

---

## Code Standards

### Pre-commit Hook

Runs automatically on `git commit` (installed via `composer install`):
1. **Laravel Pint** — fixes code style on staged PHP files
2. **PHPStan** — static analysis; commit aborted on errors

### Commands

```bash
composer run fix       # Pint: fix code style
composer run lint      # Pint: dry-run check
composer run analyse   # PHPStan (level 7 + Larastan)
composer run check     # lint + analyse together
```

### IDE Helper

```bash
php artisan ide-helper:generate   # IDE autocompletion
php artisan ide-helper:models     # model annotations
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Connection refused | Ensure `php artisan serve` is running; `lsof -i :8000` |
| Database locked | `php artisan cache:clear` then `php artisan migrate:refresh` |
| Class not found | `composer dump-autoload` |
| Tests failing | `php artisan migrate --env=testing` |

---

## Environment Configuration

```bash
APP_NAME="Shop API"
APP_ENV=local              # production: production
APP_DEBUG=true             # production: false
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite       # or pgsql / mysql
DB_DATABASE=database/database.sqlite

CACHE_STORE=array          # production: redis
QUEUE_CONNECTION=sync      # production: redis

WEBHOOK_ORDER_PAID_URL=    # leave unset to disable outbound webhooks
WEBHOOK_SIGNING_SECRET=    # HMAC-SHA256 secret for inbound verification

MAIL_MAILER=log            # production: smtp
```

---

## Deployment Checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `composer install --no-dev`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan test`
- [ ] File permissions, error logging, database backups
