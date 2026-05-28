# TVIO Public-Facing ID Strategy Report

## Summary
Currently, TVIO's public and authenticated API routes expose internal database primary keys (auto-incrementing integers). This is a potential security and business concern because it makes resource URLs predictable, allows enumeration (e.g., scraping all students by iterating IDs), and leaks business intelligence (e.g., guessing total volume of invoices or lessons). 

To resolve this, internal primary keys (`id`) should remain as Laravel's default `BIGINT UNSIGNED` for performance and relationship simplicity. However, routes and frontend responses should utilize a separate, non-predictable identifier (`public_id`) to completely decouple the database architecture from the public-facing API.

## Current Route ID Exposure
Based on an analysis of `routes/api.php`, the following routes currently expose internal numeric IDs:

| Method | Route | Current ID Param | Model/Table | Audience | Risk/Concern |
|---|---|---|---|---|---|
| GET/PATCH | `/users/{user}/profile` | `{user}` | `User` / `users` | Authenticated | High (User enumeration) |
| GET/POST | `/notifications/{notification}` | `{notification}` | `Notification` / `notifications` | Authenticated | Medium (Data exposure) |
| GET/POST | `/message-threads/{messageThread}` | `{messageThread}` | `MessageThread` / `message_threads` | Authenticated | High (Data/Communication exposure) |
| GET | `/announcements/{announcement}` | `{announcement}` | `Announcement` / `announcements` | Authenticated | Low (Public data) |
| GET | `/lessons/{lesson}/join` | `{lesson}` | `Lesson` / `lessons` | Student/Teacher | High (Predictable lesson URLs) |
| GET | `/students/{student}/*` | `{student}` | `User` / `users` | Admin/Teacher/Staff | High (Student enumeration) |
| GET | `/teachers/{teacher}/*` | `{teacher}` | `User` / `users` | Admin/Staff/Student | High (Teacher enumeration) |
| API | `/lesson-notes/{lessonNote}` | `{lessonNote}` | `LessonNote` / `lesson_notes` | Teacher/Admin/Staff | Medium |
| API | `/lesson-records/{lessonRecord}` | `{lessonRecord}` | `LessonRecord` / `lesson_records` | Admin/Staff/Teacher | Medium |
| API | `/homeworks/{homework}` | `{homework}` | `Homework` / `homeworks` | Student/Teacher/Staff | Medium |
| GET/POST | `/teacher-change-requests/{teacherChangeRequest}` | `{teacherChangeRequest}` | `TeacherChangeRequest` / `teacher_change_requests` | Student/Admin/Staff | Low |
| API | `/learning-resources/{learningResource}` | `{learningResource}` | `LearningResource` / `learning_resources` | Admin/Teacher/Staff | Medium |
| API | `/course-programs/{courseProgram}` | `{courseProgram}` | `CourseProgram` / `course_programs` | Admin/Student/Staff | Low |
| API | `/subscriptions/{subscription}` | `{subscription}` | `Subscription` / `subscriptions` | Admin/Staff | High (Financial exposure) |
| API | `/invoices/{invoice}` | `{invoice}` | `Invoice` / `invoices` | Student/Admin/Staff | High (Financial/Volume exposure) |
| API | `/class-schedules/{classSchedule}` | `{classSchedule}` | `Lesson` / `lessons` | General/Staff | Medium |

## Recommended ID Strategy
**Recommendation: ULID (Universally Unique Lexicographically Sortable Identifier)**
TVIO should implement **ULID** (`$table->ulid('public_id')->unique();`) for the public-facing IDs. 
- **Why ULID?** ULIDs are 26 characters long, base32 encoded, and URL-safe. Unlike UUID v4, they are lexicographically sortable because they contain a timestamp component, meaning they naturally sort by creation time, which is beneficial for frontend sorting and database index fragmentation.
- The internal `id` (`BIGINT`) will remain the primary key and foreign key for all internal database relationships.

## Tables Requiring public_id
The following tables represent externally referenced entities and should be prioritized for receiving a `public_id`:

| Table | Reason | Priority |
|---|---|---|
| `users` | Students, teachers, and staff are constantly referenced in routes and profiles. | High |
| `lessons` | Required for secure, non-guessable lesson joining and notes URLs. | High |
| `invoices` | Prevents exposing billing volume and allows secure invoice download links. | High |
| `subscriptions` | Prevents exposing financial status and user subscription history. | High |
| `homeworks` | Required for secure student assignments. | Medium |
| `message_threads` | Prevents unauthorized attempts to access chat threads. | Medium |
| `learning_resources` | Ensures file downloads and resources aren't sequentially scraped. | Medium |
| `course_programs` | Prevents enumeration of courses. | Low |
| `announcements` | Standardizing API responses. | Low |
| `notifications` | Standardizing API responses. | Low |
| `audit_logs` | Useful for standardizing admin API responses (if exposed). | Low |

*(Note: Profile tables like `student_profiles` or `teacher_profiles` may not need a separate `public_id` if they are accessed via the `User`'s `public_id`, depending on current API resource structure).*

## Route Binding Plan
To safely transition route model binding:
1. **Public/Student/Teacher Routes:** Use explicit route binding in `routes/api.php` by changing parameters from `{user}` to `{user:public_id}`, `{lesson}` to `{lesson:public_id}`, etc.
   - Example: `Route::get('/students/{student:public_id}/invoices', ...)`
2. **Admin Routes:** Admins can also safely use `public_id` in URLs for consistency, minimizing confusion between different API boundaries. 
3. **Model Configuration:** Do **not** globally override `getRouteKeyName()` on heavily shared models like `User` initially. Instead, use explicit scoped bindings (`{model:public_id}`) in route definitions. This gives fine-grained control and prevents accidental breakages in internal/admin tooling that might hardcode numerical IDs in requests.

## API Response Plan
To completely hide internal numeric IDs from the frontend:
1. Update Laravel API Resources (e.g., `UserResource`, `InvoiceResource`).
2. Map the frontend `id` field to the database `public_id`:
   ```php
   return [
       'id' => $this->public_id, // Expose ULID as the standard 'id'
       // ...
   ];
   ```
3. Remove the internal database `id` completely from all public, student, and teacher API responses.
4. For Admin API responses, you can optionally include an `internal_id` field alongside the `id` (ULID) for debugging purposes, but actions should be driven by the ULID.

## Migration and Backfill Plan
This transition requires a safe, multi-step deployment to avoid downtime:
1. **Schema Migration:** Create a migration to add `public_id` to all relevant tables.
   ```php
   $table->ulid('public_id')->nullable()->unique();
   ```
2. **Backfill Command:** Create an Artisan command (e.g., `php artisan tvio:backfill-public-ids`) to generate ULIDs for all existing rows in chunks. Run this command in production.
3. **Model Updates:** Update Model `booted` methods or Observers to automatically generate a ULID for newly created records.
   ```php
   static::creating(function ($model) {
       $model->public_id = (string) Str::ulid();
   });
   ```
4. **Make Non-Nullable:** Create a secondary migration to change `public_id` to strictly non-nullable after the backfill is verified.
   ```php
   $table->ulid('public_id')->nullable(false)->change();
   ```
5. **Factories/Seeders:** Update all model factories to include `'public_id' => (string) Str::ulid()`.

## Authorization Reminder
**Important:** Using ULIDs obfuscates URLs and prevents simple enumeration, but it **does not replace authorization**. 
Every controller and route must continue to enforce authorization policies (e.g., `Gate::authorize('view', $invoice)`) to ensure the authenticated user actually owns or has permission to access the specified resource, even if they somehow obtain the `public_id`.

## Implementation Tickets
To execute this plan smoothly, break the work into the following iterative tickets:

1. **Ticket 1: Database Foundation & Generation**
   - Create migrations adding `public_id` (nullable) to core tables.
   - Update model creation events/traits to auto-generate ULIDs.
   - Update factories and seeders.
2. **Ticket 2: Data Backfill**
   - Write and test the Artisan command to backfill existing production rows.
   - Deploy Ticket 1 & 2, run backfill, then deploy a migration making `public_id` non-nullable.
3. **Ticket 3: API Resources Update**
   - Update all API Resources to output `public_id` as the frontend `id`.
   - Ensure frontend applications are prepared for string-based IDs instead of integers.
4. **Ticket 4: Route Binding Transition (Public/Student/Teacher)**
   - Update `routes/api.php` public and user-facing routes to use `{model:public_id}`.
   - Update relevant controllers if they perform explicit ID lookups.
5. **Ticket 5: Route Binding Transition (Admin/Staff)**
   - Update remaining admin routes to use `{model:public_id}` for API consistency.
   - Conduct full regression testing on API endpoints.
