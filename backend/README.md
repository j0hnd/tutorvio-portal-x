# Tutorvio Portal Backend

Laravel API backend for the Tutorvio Portal monorepo.

## Stack

- PHP 8.3+
- Laravel 13
- Laravel Sanctum for token authentication
- Spatie Laravel Permission for `role:*` and `permission:*` middleware
- PHPUnit for backend tests
- Laravel Pint for PHP formatting
- L5 Swagger for OpenAPI generation and Swagger UI
- MariaDB and Redis in the local Docker stack

## Local Setup

Preferred Docker flow from the repository root:

```bash
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
```

The Docker backend is exposed at `http://localhost:8001`.

For local commands without Docker, run them from `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

The default `.env.example` is configured for the Docker MariaDB service:

- `DB_HOST=mariadb`
- `DB_PORT=3306`
- `DB_DATABASE=tutorvio`
- `DB_USERNAME=tutorvio`
- `DB_PASSWORD=tutorvio_password`

Redis is optional outside configured environments. The application queue config falls back to the database driver when `QUEUE_CONNECTION` is not set, so deployments that have not provisioned Redis are not forced onto it.

The example environment uses Redis for queue processing in the Docker stack:

```env
QUEUE_CONNECTION=redis
```

The Docker stack includes a Redis service:

```bash
docker compose up -d redis
```

Keep `CACHE_STORE=database` unless Redis-backed caching is also required.

Redis-backed cache locks use `REDIS_CACHE_LOCK_CONNECTION`, which defaults to the dedicated `lock` Redis connection.

The Docker backend image installs the `phpredis` extension for `REDIS_CLIENT=phpredis`. Non-Docker Redis use requires the same PHP extension in the local PHP runtime.

Chat realtime Redis keys, TTLs, fallback behavior, and the MariaDB source-of-truth boundary are documented in [docs/chat-redis-state.md](docs/chat-redis-state.md).

Redis environment variables:

- `REDIS_CLIENT`: Redis client, usually `phpredis`.
- `REDIS_HOST`: Redis host or Docker service name.
- `REDIS_PORT`: Redis port.
- `REDIS_USERNAME`: Redis username when required by the service.
- `REDIS_PASSWORD`: Redis password, or `null` for no password.
- `REDIS_DB`: Default Redis database.
- `REDIS_CACHE_DB`: Redis database for cache values.
- `REDIS_QUEUE_DB`: Redis database for queues.
- `REDIS_LOCK_DB`: Redis database for cache locks.
- `REDIS_CACHE_CONNECTION`: Laravel Redis connection used by the `redis` cache store.
- `REDIS_CACHE_LOCK_CONNECTION`: Laravel Redis connection used by Redis cache locks.
- `REDIS_QUEUE_CONNECTION`: Laravel Redis connection used by the `redis` queue driver.
- `REDIS_QUEUE`: Queue name for Redis jobs.
- `REDIS_QUEUE_RETRY_AFTER`: Seconds before a Redis job is retried.
- `REDIS_QUEUE_BLOCK_FOR`: Optional seconds for Redis queue blocking pop; use `null` to disable blocking.

### Queue Worker

The Docker stack includes a dedicated `queue` service that runs the Redis queue worker:

```bash
docker compose up -d queue
docker compose logs -f queue
```

`docker compose up -d` starts this worker with the rest of the stack. The service runs `php artisan queue:work redis --queue=default --tries=3` and consumes jobs from the Docker Redis service.

For a host PHP runtime from `backend/`:

```bash
php artisan queue:work redis --queue=default --tries=3
```

Use `database` instead of `redis` if the local `.env` keeps `QUEUE_CONNECTION=database`. If `REDIS_QUEUE` is changed from `default`, update the Compose service command and pass that queue name to `--queue`.

## Common Commands

Run from `backend/` unless using `docker compose exec backend`.

```bash
php artisan test
composer test
vendor/bin/pint
php artisan migrate
php artisan migrate:fresh --seed
php artisan queue:work redis --queue=default --tries=3
php artisan route:list --path=api --json
php artisan l5-swagger:generate
```

Use `php artisan route:list --path=api --json` for API route inventory. This Laravel checkout does not support the `--columns` option.

## API Shape

Primary API routes are versioned under `/api/v1` in `routes/api.php`.

Current public or compatibility routes outside `/api/v1`:

- `GET /api/settings/public` for public portal settings, throttled by `api-public`.
- `GET /api/admin/settings` and `PUT|PATCH /api/admin/settings` as legacy admin settings compatibility routes protected by Sanctum, `role:admin|staff`, and portal settings permissions.

Protected routes use Sanctum authentication. Admin and staff workflows add role and permission middleware on top of `auth:sanctum`.

Fresh installs seed the `staff` role without baseline permissions. Staff access is intentionally granted by assigning named permissions such as `users.view`, `school_reports.view`, or other route-specific permissions after the user's operational scope is known.

## Public IDs

Client-facing resource identifiers use `public_id` ULIDs. API resources should expose `public_id` as the normal client-facing `id` field and should not expose internal numeric database IDs unless a deliberately internal admin field is required.

Route model bindings for public API resources should prefer explicit public ID binding:

```php
{lesson:public_id}
```

The rollout and production backfill guidance is documented in [docs/public-id-rollout.md](docs/public-id-rollout.md).

Useful public ID checks:

```bash
php artisan tvio:backfill-public-ids
php artisan tvio:backfill-public-ids --chunk=500
php artisan test --filter=PublicIdGenerationTest
php artisan test --filter=BackfillPublicIdsCommandTest
php artisan test --filter=PublicIdAuthorizationRegressionTest
```

## Method Documentation

Use method documentation to explain behavior that is not obvious from the method name or type signature:

- Controllers should describe API behavior, request expectations, response shape, and access rules.
- Services should describe business logic, important decisions, and side effects such as writes, events, notifications, or external calls.
- Models should describe relationships, query scopes, casts, and domain-specific helpers that affect persistence or retrieval.
- Policies should describe authorization rules, role and permission requirements, and ownership or state checks.
- Resources should describe public-safe response fields and call out intentionally hidden internal or sensitive fields.
- Avoid comments that simply repeat the method name or restate obvious code.
- Keep documentation updated whenever behavior, access rules, side effects, or response fields change.

## OpenAPI And Swagger

OpenAPI annotations live under `app/OpenApi` and related application classes. Generate the OpenAPI JSON with:

```bash
php artisan l5-swagger:generate
```

Generated docs are written to `storage/api-docs/api-docs.json`.

Swagger routes:

- `GET /docs` serves the generated JSON.
- `GET /api/documentation` serves Swagger UI.

Swagger access is controlled by `App\Http\Middleware\AllowSwaggerAccess` and `config/l5-swagger.php`.

Relevant environment variables:

- `L5_SWAGGER_API_TITLE`
- `L5_SWAGGER_API_VERSION`
- `L5_SWAGGER_API_DESCRIPTION`
- `L5_SWAGGER_CONST_HOST`
- `L5_SWAGGER_ALLOWED_ENVIRONMENTS`
- `L5_SWAGGER_ALLOW_PRODUCTION`

By default, Swagger is available only in `local` and `testing`. Production access stays disabled unless explicitly allowed.

Focused Swagger verification:

```bash
php artisan test tests/Feature/SwaggerDocumentationTest.php
```

For Docker-specific Swagger failures, verify with the same path:

```bash
docker compose exec backend php artisan test tests/Feature/SwaggerDocumentationTest.php
```

## Security And Access Controls

The backend currently includes these hardening conventions:

- API exception responses are normalized in `bootstrap/app.php` for validation, authentication, authorization, not-found, throttling, and generic server errors.
- Sensitive request fields are listed in `dontFlash` so secrets are not reflected in validation errors.
- Logging channels are tapped by `App\Logging\SanitizeLogContext`; sanitization rules live in `App\Support\LogSanitizer`.
- Sensitive auth routes are rate limited through named limiters such as `auth-login`, `auth-register`, `auth-password-reset`, `auth-invite`, and `auth-invitation-accept`.
- API actions, uploads, downloads, reports, and public endpoints use named throttles such as `api-action`, `api-upload`, `api-download`, `api-report`, and `api-public`.
- List endpoints should keep explicit pagination limits instead of allowing unbounded result sets.
- User file path fields must be validated before storage or response use.
- Archived academic records are not accessible to students or teachers even when they own or are assigned to the record; admin and permitted staff access is handled separately.
- Invoice audit behavior preserves the safe invoice audit reference flag without exposing unsafe payment or billing references.

## Authorization

Policies are registered in `App\Providers\AppServiceProvider`. Domain access should be enforced through policies, gates, and route middleware rather than controller-only conditionals when possible.

Common middleware:

- `auth:sanctum`
- `role:admin`
- `role:admin|staff`
- `role:student`
- `permission:<permission-name>`

When changing route security, verify the generated route output instead of relying only on the route group shape:

```bash
php artisan route:list --path=api -vv
php artisan route:list --path=api/admin/settings -vv
```

## Testing

Use the narrowest useful test command for the change:

```bash
php artisan test --filter=AcademicRecordApiTest
php artisan test --filter=AuthSecurityTest
php artisan test --filter=LogSanitizationTest
php artisan test --filter=UserResponseSecurityTest
php artisan test --filter=SwaggerDocumentationTest
```

Run the full backend test suite when the change crosses domains or shared behavior:

```bash
php artisan test
```

Format PHP changes before handoff:

```bash
vendor/bin/pint
```

## Development Notes

- Keep new routes under `/api/v1` unless maintaining a documented compatibility endpoint.
- Prefer controllers, Form Request validation where useful, Eloquent relationships, policies, resources, and service classes over custom plumbing.
- Keep raw internal IDs out of client responses; use `App\Http\Resources\Concerns\SanitizesApiResponses` helpers where appropriate.
- Update OpenAPI annotations when route behavior, response examples, security requirements, or public ID fields change.
- Add focused Feature tests for API behavior and Unit tests for isolated domain logic.
