# AGENTS.md

Guidance for coding agents working in this repository.

## Project Overview

Tutorvio Portal is a monorepo with two primary applications:

- `backend/`: Laravel API backend using PHP 8.3+, Laravel 13, Sanctum, PHPUnit, and Pint.
- `frontend/`: Vue 3 + Vite + TypeScript frontend using Pinia, Vue Router, and Axios.
- `docker-compose.yml`: Local Docker development stack with backend, frontend, nginx, and MariaDB.

## Repository Layout

- `backend/app/`: Laravel application code.
- `backend/routes/api.php`: Versioned API routes under `/api/v1`.
- `backend/database/migrations/`: Schema changes.
- `backend/database/seeders/`: Seed data, including roles and permissions.
- `backend/tests/Feature/` and `backend/tests/Unit/`: Backend test suites.
- `frontend/src/`: Vue application source.
- `frontend/src/app/router.ts`: Frontend route definitions.
- `frontend/src/lib/axios.ts`: Shared Axios configuration.
- `nginx/`: Reverse proxy configuration for the Docker stack.

## Local Development

Preferred Docker flow from the repository root:

```bash
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec frontend npm install
docker compose exec frontend npm run dev
```

Local service URLs:

- Backend API: `http://localhost:8001`
- Frontend app: `http://localhost:5173`
- MariaDB: `localhost:3307`

The root README currently mentions backend port `8000`, but `docker-compose.yml` maps host port `8001` to container port `8000`. Prefer the compose file when in doubt.

## Common Commands

Run backend commands from `backend/` unless using `docker compose exec backend`.

```bash
composer test
php artisan test
vendor/bin/pint
php artisan migrate
php artisan migrate:fresh --seed
```

Run frontend commands from `frontend/` unless using `docker compose exec frontend`.

```bash
npm run dev
npm run build
npm run preview
```

## Backend Conventions

- Keep API routes versioned under `Route::prefix('v1')` in `backend/routes/api.php`.
- Use Laravel controllers for non-trivial request handling; keep route closures limited to tiny health checks or simple framework defaults.
- Prefer Laravel validation, policies/middleware, Eloquent relationships, resources, and framework helpers over custom plumbing.
- Sanctum is the auth mechanism; protected API routes should use `auth:sanctum`.
- Existing role and permission middleware names are `role:*` and `permission:*`.
- When changing schema, add or update migrations and include seed updates when required for local setup.
- Add focused Feature tests for API behavior and Unit tests for isolated domain logic.
- Format PHP with Pint before handing off when PHP files changed.

## Frontend Conventions

- Use Vue single-file components with `<script setup lang="ts">`.
- Keep shared app wiring in `frontend/src/app`, shared HTTP setup in `frontend/src/lib`, and reusable UI in `frontend/src/components`.
- Use TypeScript types for component props, API payloads, and route-facing data.
- Prefer Vue Router for navigation and Pinia for cross-page state.
- Keep API calls centralized behind Axios helpers or small service modules instead of scattering raw URLs through components.
- Build responsive UI directly in the app experience; avoid landing-page filler when implementing product workflows.
- Run `npm run build` after frontend changes to catch TypeScript and Vite issues.

## Testing And Verification

Before finishing a change, run the narrowest useful checks:

- Backend-only changes: `composer test` or `php artisan test`; run `vendor/bin/pint` for formatting.
- Frontend-only changes: `npm run build`.
- Cross-stack changes: backend tests plus frontend build; use Docker if the change depends on services.

If a check cannot be run because dependencies or services are unavailable, report that clearly with the attempted command.

## Working Rules

- Do not overwrite user changes. Check `git status --short` before editing and treat unrelated dirty files as user-owned.
- Keep edits scoped to the requested behavior.
- Do not commit unless explicitly asked.
- Do not add new dependencies without a clear need and an explanation.
- Prefer existing project patterns over introducing new architecture.
- Update this file when commands, ports, or project structure materially change.
