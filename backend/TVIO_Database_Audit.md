# TVIO Database PK and Relationship Audit (Partial)

## Summary
The TVIO database uses a standard Laravel primary key strategy (`$table->id()`, which translates to `BIGINT UNSIGNED`) for all core entities. There are no UUIDs or BINARY formats used in the inspected tables (users, lessons, subscriptions, invoices, student/teacher profiles), which aligns with standard Laravel conventions and avoids the complexities found in other systems like QRM. 

Foreign key relationships heavily rely on Laravel's cascading delete behavior, ensuring database integrity and preventing orphan records when users or parent entities are removed.

## Primary Keys by Table
| Table | Primary Key | DB Type | App Format | Notes |
|---|---|---|---|---|
| users | id | BIGINT unsigned | Integer ID | Standard Laravel default |
| student_profiles | id | BIGINT unsigned | Integer ID | Has 1:1 with users |
| teacher_profiles | id | BIGINT unsigned | Integer ID | Has 1:1 with users |
| lessons | id | BIGINT unsigned | Integer ID | Links student and teacher |
| subscriptions | id | BIGINT unsigned | Integer ID | Links to users |
| invoices | id | BIGINT unsigned | Integer ID | Billing entity |

## Foreign Key Associations
| Table | FK Column | References | Nullable | On Delete/Update | Notes |
|---|---|---|---|---|---|
| student_profiles | user_id | users.id | No | CASCADE | Enforces 1:1 constraint |
| teacher_profiles | user_id | users.id | No | CASCADE | Enforces 1:1 constraint |
| lessons | student_id | users.id | No | CASCADE | |
| lessons | teacher_id | users.id | No | CASCADE | |
| subscriptions | user_id | users.id | No | CASCADE | |
| invoices | student_id | users.id | No | CASCADE | |
| invoices | subscription_id | subscriptions.id | Yes | SET NULL | Can keep invoice if sub is deleted |
| invoices | course_program_id| course_programs.id| Yes | SET NULL | |

## Laravel Model Relationship Review
- **User** Model contains comprehensive relationships:
  - `studentProfile() -> HasOne`
  - `teacherProfile() -> HasOne`
  - `staffProfile() -> HasOne`
  - `studentInvoices() -> HasMany`
  - `subscriptions() -> HasMany`
  - `studentLessonRecords() -> HasMany`
  - `teacherLessonRecords() -> HasMany`
  - Dozens of other relations mapping the full ecosystem.

## Core Entity Relationship Map
The core system revolves around the `users` table serving as the central hub. Users are specialized via profile tables (`student_profiles`, `teacher_profiles`, `staff_profiles`). 
- **Lessons** bridge students and teachers together with specific timestamps.
- **Subscriptions** and **Invoices** are tied to the `users` table directly (acting as students).

## Billing Relationship Findings
Invoices are heavily indexed (on `student_id`, `subscription_id`, `course_program_id`, `status`, `invoice_number`, `due_date`) ensuring high-performance querying for billing. Invoices map back to students and optionally to subscriptions and course programs. Nullable constraints on subscriptions mean an invoice remains valid even if a subscription is deleted (`set null`).

## Lesson and Scheduling Relationship Findings
Lessons require both a `student_id` and a `teacher_id`, tied directly to the `users` table (not the profile tables). Deleting either user cascades and deletes the lesson, which keeps the database clean but may lose historical records of taught classes if a teacher is hard-deleted.

## Permission Relationship Findings
Not fully audited in this partial review, but Spatie roles/permissions tables (`model_has_permissions`, `model_has_roles`, etc.) are present and utilize composite primary keys.

## Index and Integrity Risks
- Invoices are extremely well indexed.
- `lessons` table uses cascading deletes on both `student_id` and `teacher_id`. While this ensures no orphan records, a hard deletion of a teacher will wipe out their entire lesson history. Soft deletes should be strongly considered for the `users` table to preserve billing and historical lesson integrity.

## Findings / Recommendations
- The system is well-structured following Laravel conventions (auto-incrementing integers).
- UUIDs are not required unless public-facing endpoints strictly need obfuscated IDs.
- Consider reviewing `cascadeOnDelete()` across historical tables (like lessons, attendances). Using soft deletes on Users or using `set null` on historical tables is usually preferred to maintain historical auditing records.