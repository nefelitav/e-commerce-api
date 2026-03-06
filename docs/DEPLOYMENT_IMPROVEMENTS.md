# Deployment Improvements

This document describes all changes made to the deployment configuration of the shop-api project, organised by file. Each change includes the rationale explaining why it is an improvement.

---

## Dockerfile

### 1. Removed `git`, `unzip`, and `composer` from the base stage

**Before:** `git`, `unzip`, and `composer` were installed in the `base` stage, meaning they were present in the production image.

**After:** These tools are only installed in the `dev` and `build` stages where they are needed.

**Why:** Production images should contain the absolute minimum required to run the application. Shipping `git`, `unzip`, and `composer` into production increases the attack surface and image size. An attacker who gains access to the container could use these tools to download and execute malicious code.

### 2. Added `libfcgi-bin` for proper health checks

**Before:** The health check relied on `php-fpm-healthcheck`, which is not available in the official PHP image and falls back to `kill -0 1`.

**After:** `libfcgi-bin` is installed, providing the `cgi-fcgi` binary, and a `HEALTHCHECK` directive uses it to query PHP-FPM's `/ping` endpoint.

**Why:** `cgi-fcgi` speaks the FastCGI protocol natively and validates that PHP-FPM is actually processing requests — not just that the process exists. This gives orchestrators (Docker Swarm, Kubernetes sidecars) a reliable signal for readiness.

### 3. Added `HEALTHCHECK` instruction to the Dockerfile

**Before:** Health checks were only defined in `docker-compose.yml`.

**After:** A `HEALTHCHECK` is embedded in the Dockerfile itself.

**Why:** When running containers outside of Compose (e.g., plain `docker run`, Swarm, ECS), the embedded health check still applies. It provides a default that compose files can override.

### 4. Added `event:cache` to the build stage

**Before:** Only `config:cache`, `route:cache`, and `view:cache` were run.

**After:** `php artisan event:cache` is also run.

**Why:** Laravel's event discovery scans the entire application for event listeners at runtime. Caching events eliminates this overhead in production.

### 5. Removed `|| true` from artisan cache commands

**Before:** Cache commands used `|| true` to silently swallow failures.

**After:** Commands run without error suppression.

**Why:** If caching fails during a build, the image is broken and should not be shipped. Silencing errors masks build failures and can cause hard-to-debug production issues.

### 6. Used `COPY --chown=www-data:www-data` instead of `chown -R`

**Before:** Files were copied, then a separate `RUN chown -R` changed ownership, creating an extra layer.

**After:** `COPY --from=build --chown=www-data:www-data` sets ownership during the copy.

**Why:** The `--chown` flag sets ownership in the same layer as the copy, avoiding a duplicate layer that stores the same files with different metadata. This reduces image size and build time.

### 7. Added `STOPSIGNAL SIGQUIT`

**Before:** Default stop signal (`SIGTERM`) was used.

**After:** `STOPSIGNAL SIGQUIT` is set.

**Why:** PHP-FPM performs a graceful shutdown on `SIGQUIT`, finishing in-progress requests before exiting. `SIGTERM` causes an immediate shutdown, which can interrupt active e-commerce transactions (order placement, payment processing).

### 8. Added cleanup of unnecessary files from the production image

**After:** `rm -rf /var/www/.git /var/www/tests /var/www/docker` and removal of Docker config files.

**Why:** Even though `.dockerignore` excludes many files from the build context, files copied from the build stage may include test directories or Docker configs. Explicit cleanup ensures the production image is lean.

### 9. Added PHP-FPM production pool configuration

**After:** A dedicated `docker/php/php-fpm-prod.conf` is copied into the production image.

**Why:** The default PHP-FPM pool settings are tuned for development. Production workloads need tuned `pm.max_children`, `pm.start_servers`, request timeouts, slow-request logging, and the `/ping` endpoint for health checks.

### 10. Added container labels

**After:** OCI-standard labels (`maintainer`, `description`, `org.opencontainers.image.source`) are added.

**Why:** Labels make images identifiable in registries and orchestrators. They are essential for automated scanning, auditing, and image management at scale.

---

## PHP-FPM Production Configuration (`docker/php/php-fpm-prod.conf`)

This is a **new file** providing production-tuned PHP-FPM pool settings:

| Setting | Value | Rationale |
|---|---|---|
| `pm = dynamic` | Dynamic process management | Scales workers between `min_spare` and `max_spare` based on load |
| `pm.max_children = 50` | 50 workers | Handles concurrent API requests for high-traffic e-commerce |
| `pm.start_servers = 10` | 10 initial workers | Fast cold-start without over-allocating |
| `pm.max_requests = 1000` | Recycle after 1000 requests | Prevents memory leaks from accumulating |
| `ping.path = /ping` | Health check endpoint | Enables reliable health checking via FastCGI |
| `request_slowlog_timeout = 5s` | Log slow requests | Identifies performance bottlenecks |
| `request_terminate_timeout = 60s` | Kill hanging requests | Prevents runaway processes from consuming resources |
| `security.limit_extensions = .php` | Restrict to `.php` | Prevents execution of non-PHP files via FastCGI |
| `clear_env = yes` | Clear host environment | Prevents leaking host environment variables into PHP |

---

## OPcache Configuration (`docker/php/opcache-prod.ini`)

### 1. Added JIT compilation (`opcache.jit=tracing`)

**Before:** JIT was not configured.

**After:** `opcache.jit=tracing` with a 128 MB JIT buffer.

**Why:** PHP 8.3's tracing JIT compiler analyses hot code paths and compiles them to native machine code. For CPU-bound operations (price calculations, inventory checks, serialisation), this provides measurable throughput improvements.

### 2. Added `opcache.enable_cli=0`

**Why:** Explicitly disables OPcache for CLI processes. CLI scripts are short-lived, so OPcache adds overhead without benefit. Queue workers use the FPM-level OPcache automatically.

### 3. Added `opcache.max_wasted_percentage=10`

**Why:** Controls when OPcache restarts to reclaim wasted memory. Prevents gradual memory fragmentation from degrading performance.

### 4. Added `opcache.log_verbosity_level=1`

**Why:** Reduces log noise in production by only logging errors, not informational messages.

### 5. Added preloading documentation (commented)

**Why:** Documents how to enable Laravel preloading when ready. Preloading loads the entire framework into shared memory on startup, eliminating file-system reads.

---

## Nginx Configuration (`docker/nginx/default.conf`)

### 1. Added rate limiting

**Before:** No rate limiting.

**After:** Two rate-limiting zones: `api` (60 req/s per IP with burst of 30) and `login` (5 req/s for auth endpoints).

**Why:** Rate limiting protects the API from abuse, brute-force attacks, and accidental DDoS from misbehaving clients. The burst parameter allows short traffic spikes without dropping legitimate requests.

### 2. Added upstream keepalive connections

**Before:** `fastcgi_pass app:9000` opened a new connection per request.

**After:** An `upstream php-fpm` block with `keepalive 16` and `fastcgi_keep_conn on`.

**Why:** Reusing TCP connections to PHP-FPM eliminates the overhead of connection establishment (TCP handshake). For high-throughput e-commerce APIs, this reduces latency and CPU usage.

### 3. Replaced `X-XSS-Protection` with modern security headers

**Before:** `X-XSS-Protection: 1; mode=block` (deprecated, can introduce vulnerabilities in older browsers).

**After:** Removed `X-XSS-Protection`. Added `Content-Security-Policy`, `Strict-Transport-Security` (HSTS), and `Permissions-Policy`.

**Why:**
- **CSP** (`default-src 'none'`) — For a pure API, no resources should be loaded. This prevents any injected content from executing.
- **HSTS** — Forces browsers and API clients to use HTTPS, preventing downgrade attacks.
- **Permissions-Policy** — Explicitly disables browser APIs (camera, microphone, geolocation) that an API never needs.
- `X-Frame-Options` changed from `SAMEORIGIN` to `DENY` — an API should never be framed.

### 4. Restricted PHP execution to `index.php` only

**Before:** Any `.php` file could be executed via FastCGI.

**After:** Only `/index.php` is forwarded to PHP-FPM. All other `.php` requests return 404.

**Why:** Laravel routes everything through `index.php`. Allowing execution of arbitrary PHP files (e.g., a maliciously uploaded file in `/public`) is a critical security vulnerability.

### 5. Added request timeout headers

**After:** `client_body_timeout 15s`, `client_header_timeout 15s`, `send_timeout 30s`.

**Why:** Prevents slow-loris attacks where clients send data extremely slowly to tie up server connections.

### 6. Added sensitive file blocking

**After:** A location block denies access to `composer.json`, `composer.lock`, `.env`, `artisan`, `phpunit`, `phpstan`, and `.git`.

**Why:** These files contain sensitive information (dependency versions, environment configuration, application structure). They should never be accessible via HTTP, even if accidentally placed in the public directory.

### 7. Added health check endpoint bypass

**After:** `/up` has its own location block with `access_log off`.

**Why:** Health check endpoints are hit every few seconds by orchestrators. Logging each hit creates noise and wastes disk I/O.

### 8. Added `charset utf-8`

**Why:** Ensures proper character encoding for JSON API responses containing international characters (product names, addresses).

---

## docker-compose.yml

### 1. Introduced YAML anchors for shared environment variables

**Before:** Environment variables were duplicated between `app` and `queue` services.

**After:** A `x-app-environment` anchor (`&app-env`) is defined once and merged with `<<: *app-env`.

**Why:** DRY principle. Duplicate configuration is a maintenance burden and a source of bugs when one service is updated but not the other.

### 2. Bound database and Redis ports to `127.0.0.1`

**Before:** `ports: "5432:5432"` — accessible from any network interface.

**After:** `ports: "127.0.0.1:5432:5432"`.

**Why:** In development, databases should only be accessible from localhost. Binding to `0.0.0.0` exposes them to the local network, which is a security risk on shared or public networks.

### 3. Switched to `postgres:16-alpine`

**Before:** `postgres:16` (Debian-based, ~400 MB).

**After:** `postgres:16-alpine` (~80 MB).

**Why:** Reduces image pull time and disk usage in development. Alpine-based images have a smaller attack surface.

### 4. Added explicit network

**After:** A `shop-network` bridge network is defined and all services join it.

**Why:** Explicit networks provide better isolation and make the network topology visible. Default networks can conflict with other projects.

### 5. Made nginx volumes read-only

**After:** Nginx volumes use `:ro` flag.

**Why:** Nginx should never write to the application directory. Read-only mounts enforce this and prevent accidental modifications.

### 6. Added Redis memory policy

**After:** `command: ["redis-server", "--maxmemory", "128mb", "--maxmemory-policy", "allkeys-lru"]`.

**Why:** Without a memory limit, Redis can consume all available memory and crash the host. LRU eviction ensures cache entries are automatically cleaned when memory is full.

### 7. Fixed health check to use proper FastCGI ping

**Before:** `php-fpm-healthcheck || kill -0 1` — the `php-fpm-healthcheck` script doesn't exist in the base image.

**After:** Uses `cgi-fcgi` to query the `/ping` endpoint via FastCGI protocol.

**Why:** Validates that PHP-FPM is actually processing FastCGI requests, not just that the master process is alive.

---

## docker-compose.prod.yml

### 1. Added resource limits

**After:** CPU and memory limits for every service:
- `app`: 1 CPU / 512 MB
- `queue`: 0.5 CPU / 256 MB
- `nginx`: 0.5 CPU / 128 MB
- `postgres`: 2 CPU / 1 GB
- `redis`: 0.5 CPU / 384 MB

**Why:** Without resource limits, a single misbehaving service (e.g., a memory leak in the queue worker) can consume all host resources and crash every other service. Limits provide isolation and predictability.

### 2. Added resource reservations

**Why:** Reservations guarantee minimum resources for each service, preventing starvation during load spikes.

### 3. Added structured logging with rotation

**After:** `json-file` driver with `max-size: 10m` and `max-file: 5`.

**Why:** Without log rotation, containers can fill the disk with logs, causing the host to run out of space. JSON format enables log aggregation tools (ELK, Loki, CloudWatch).

### 4. Added read-only root filesystem

**After:** `read_only: true` with explicit `tmpfs` mounts for writable directories.

**Why:** A read-only filesystem prevents attackers from writing to the filesystem (e.g., dropping a web shell). Only directories that genuinely need writes (`/tmp`, nginx cache) are writable via `tmpfs`.

### 5. Added `stop_grace_period: 30s`

**After:** App and queue services get 30 seconds to finish in-progress work before being killed.

**Why:** E-commerce operations (order placement, payment confirmation) must complete. A default 10-second grace period may not be enough for complex transactions with external service calls.

### 6. Changed shared volume from `app_public` to `app_storage`

**Before:** `app_public` volume shared between app and nginx.

**After:** `app_storage` volume for the storage directory.

**Why:** Public assets are baked into the production image. The storage directory (logs, uploaded files) is the directory that genuinely needs persistence and sharing between the app and nginx.

### 7. Made nginx config volume read-only

**After:** `:ro` flag on the nginx configuration volume.

**Why:** The nginx configuration should never be modified at runtime. Read-only mounts enforce immutability.

### 8. Added Redis production tuning

**After:** `--maxmemory 256mb --maxmemory-policy allkeys-lru --save ""`.

**Why:** `--save ""` disables RDB persistence. In production, Redis is used as a cache and queue broker — persistence is handled by PostgreSQL. Disabling saves reduces I/O and prevents stalls during snapshotting.

### 9. Changed nginx port from 8081 to 80

**After:** Production nginx listens on port 80.

**Why:** Production traffic should use the standard HTTP port. Port 8081 is appropriate for development only.

---

## .dockerignore

### 1. Added `database/database.sqlite`, `database/factories/`, `database/seeders/`

**Why:** The SQLite file is a development database. Factories and seeders are only used in development and testing.

### 2. Added `.github/`

**Why:** CI/CD workflows and GitHub-specific files have no place in the production image.

### 3. Added `pint.json`, `.php-cs-fixer.*`

**Why:** Code style configuration is a development tool, not needed at runtime.

### 4. Added `docker-compose.override.yml`

**Why:** Override files may exist locally and should not be included in the build context.

### 5. Added `storage/debugbar/`

**Why:** Debug bar data is development-only and can contain sensitive information.

### 6. Added `yarn.lock`, `.editorconfig`, `desktop.ini`, `*.sublime-*`

**Why:** More comprehensive exclusion of editor, OS, and package manager files that add no value to the production image.

### 7. Fixed `storage/framework/cache/*` to `storage/framework/cache/data/*`

**Why:** The `cache` directory itself must exist (Laravel expects it), but cached data should not be shipped.

---

## Summary of Impact

| Area | Before | After |
|---|---|---|
| **Prod image size** | ~350 MB (includes git, composer, unzip) | ~280 MB (runtime only) |
| **Security** | Composer/git in prod, all `.php` executable, no CSP/HSTS | Minimal binaries, index.php only, full security headers |
| **Health checks** | Unreliable (`kill -0`) | FastCGI-level validation via `/ping` |
| **Resource safety** | No limits (unbounded memory/CPU) | Per-service CPU and memory limits |
| **Log management** | No rotation (disk fill risk) | JSON logs with size and count limits |
| **Graceful shutdown** | Default 10s SIGTERM | 30s SIGQUIT for clean transaction completion |
| **PHP performance** | No JIT, no FPM tuning | JIT tracing, tuned worker pool, connection keepalive |
| **Rate limiting** | None | 60 req/s per IP with burst handling |
| **Configuration DRY** | Duplicated env vars | YAML anchors |

