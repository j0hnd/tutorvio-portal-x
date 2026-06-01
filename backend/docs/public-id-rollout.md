# TVIO Public ID Rollout Guide

This guide documents the safe deployment process for moving TVIO API URLs and API response identifiers from internal numeric database IDs to `public_id` ULIDs.

## Why TVIO Uses `public_id`

TVIO uses `public_id` for records that appear in API responses, URLs, route parameters, download links, and frontend state. Public IDs prevent clients from seeing sequential database IDs, which reduces easy enumeration and avoids leaking business volume signals such as the number of users, lessons, invoices, subscriptions, or messages.

The public API should expose `public_id` values as the normal client-facing `id` field. Frontend code should treat every resource ID and relationship ID returned by the API as a string.

## Why Internal `id` Remains the Primary Key

The internal `id` column stays as the database primary key and the value used for internal foreign keys. Keeping integer primary keys preserves the existing schema shape, keeps joins and indexes compact, and avoids a risky rewrite of every relationship, factory, migration, policy, and service layer at the same time.

`public_id` is an API boundary identifier. It is not a replacement for the internal primary key.

## Why ULID Was Chosen

TVIO uses ULIDs for `public_id` because they are:

- URL-safe string identifiers.
- Non-sequential enough for public API use compared with auto-incrementing integers.
- Lexicographically sortable by creation time, which is useful for operational debugging and index locality.
- Supported directly by Laravel migrations via `$table->ulid('public_id')`.

## Security Warning

ULIDs do not replace authorization.

Every controller, policy, middleware, and query scope must still verify that the authenticated user can access the requested resource. A valid `public_id` only identifies a row; it does not prove the caller owns it or has permission to view, update, download, or delete it.

## Deployment Order

Use a staged rollout. Do not combine schema changes, backfill, non-null constraints, route binding, API response changes, and frontend changes into one risky deployment.

1. Add nullable `public_id` columns.
   - Add `public_id` to each public API table as nullable and unique.
   - Example: `$table->ulid('public_id')->nullable()->unique()->after('id');`
   - Keep existing API behavior unchanged in this phase.
2. Add automatic ULID generation.
   - Add the `HasPublicId` model concern or equivalent creation hook to every model that owns a `public_id`.
   - New records must receive a ULID before the backfill starts so the null set only shrinks.
3. Deploy and run the backfill command.
   - Deploy the nullable columns and automatic generation first.
   - Run the Artisan command against production data.
4. Verify no null public IDs remain.
   - Confirm every target table has zero rows where `public_id is null`.
   - Re-run the backfill if any nulls remain.
5. Make `public_id` non-nullable.
   - Deploy a follow-up migration that changes `public_id` to non-nullable only after verification passes.
6. Update API resources.
   - Return `public_id` as the client-facing `id`.
   - Do not expose internal numeric IDs from public API resources.
   - Relationship fields such as `student_id`, `teacher_id`, `invoice_id`, `subscription_id`, and `lesson_id` should also return public IDs when they are consumed by clients.
7. Update route binding.
   - Change public routes from internal-key binding to explicit public-key binding.
   - Example: `{lesson}` becomes `{lesson:public_id}`.
   - Prefer explicit route binding over globally overriding `getRouteKeyName()` on shared models unless the whole application is ready for that behavior.
8. Update tests and frontend usage.
   - Tests should create and assert string IDs.
   - Frontend route params, payload IDs, Pinia state, TypeScript interfaces, and comparison logic should treat IDs as strings.

## Running the Backfill Command

Run the command from `backend/`:

```bash
php artisan tvio:backfill-public-ids
```

To control batch size:

```bash
php artisan tvio:backfill-public-ids --chunk=500
```

In Docker, run it from the repository root:

```bash
docker compose exec backend php artisan tvio:backfill-public-ids
```

The command prints a table with each target table, pending count, updated count, remaining null count, and status. A table is ready for the non-null migration only when its remaining count is `0`.

Current command target tables are defined in `App\Console\Commands\BackfillPublicIds::TABLES`. When adding a new public API table, update that list and the rollout tests for the command.

## Verifying No Null IDs Remain

Use the command output first. For a direct database check, query each target table:

```sql
select count(*) as missing_public_ids from users where public_id is null;
select count(*) as missing_public_ids from lessons where public_id is null;
select count(*) as missing_public_ids from lesson_notes where public_id is null;
select count(*) as missing_public_ids from lesson_records where public_id is null;
select count(*) as missing_public_ids from class_schedules where public_id is null;
select count(*) as missing_public_ids from invoices where public_id is null;
select count(*) as missing_public_ids from subscriptions where public_id is null;
select count(*) as missing_public_ids from homeworks where public_id is null;
select count(*) as missing_public_ids from message_threads where public_id is null;
select count(*) as missing_public_ids from learning_resources where public_id is null;
select count(*) as missing_public_ids from course_programs where public_id is null;
select count(*) as missing_public_ids from course_types where public_id is null;
select count(*) as missing_public_ids from student_progress_records where public_id is null;
select count(*) as missing_public_ids from academic_records where public_id is null;
select count(*) as missing_public_ids from teacher_change_requests where public_id is null;
select count(*) as missing_public_ids from announcements where public_id is null;
select count(*) as missing_public_ids from notifications where public_id is null;
select count(*) as missing_public_ids from audit_logs where public_id is null;
```

Every query must return `0` before making `public_id` non-nullable.

## Tests By Phase

Run focused tests for the phase being implemented. Do not default to the full backend suite for this rollout unless the change is broad enough to require it.

- Nullable columns and automatic generation:
  - `php artisan test --filter=PublicIdGenerationTest`
  - Relevant model tests for newly covered models.
- Backfill command:
  - `php artisan test --filter=BackfillPublicIdsCommandTest`
- Non-null migration:
  - Migration smoke check in the target environment.
  - Re-run `php artisan test --filter=PublicIdGenerationTest` if generation code changed.
- API resources:
  - Resource-owning API tests for touched domains, such as invoices, lessons, homeworks, messages, subscriptions, scheduling, course catalog, and profiles.
  - Add assertions that public response IDs are strings and internal numeric IDs are absent.
- Route binding:
  - Domain API tests for every route group changed to `{model:public_id}`.
  - `php artisan test --filter=PublicIdAuthorizationRegressionTest`
- Frontend usage:
  - `npm run build` from `frontend/` after TypeScript ID types are updated.
  - Focused UI or integration checks for workflows whose route params or payloads changed.

## Frontend Notes

Frontend IDs are now strings.

TypeScript types should use `string` for resource IDs and relationship IDs. Avoid numeric parsing, arithmetic comparisons, or assumptions that a larger ID means a newer record. If ordering is needed, use explicit API fields such as `created_at`, scheduled dates, invoice dates, or server-provided sort order.

When building URLs, use the string `id` returned by the API. Do not depend on `internal_id`, database IDs, or local casts to `number`.
