# Lesson-Based Meeting Room Generation

Date: 2026-05-29

## Scope

This document investigates whether Tutorvio can generate one meeting room per lesson and proposes a backend data model and lifecycle. It is a design investigation only; no implementation is included.

Target behavior:

- Each lesson can have one meeting room.
- The meeting room is created automatically when needed.
- The room is linked to `lesson_id`.
- Teacher and student join the same room.
- Room access is blocked if the lesson is cancelled, completed, expired, rescheduled, missed, or unauthorized.
- Room metadata is stored safely.
- Provider meeting IDs, room names, join URLs, recording IDs, and status fields do not expose sensitive credentials.

## Current Backend State

Per-lesson rooms are supported conceptually, but not yet as a first-class backend model.

The current backend stores meeting fields directly on `lessons`:

- `meeting_link`
- `meeting_provider`
- `meeting_metadata`
- `join_available_from`
- `join_available_until`

These fields were added in `backend/database/migrations/2026_05_21_040000_add_meeting_access_fields_to_lessons_table.php`.

The current join endpoint is:

- `GET /api/v1/lessons/{lesson}/join`

This endpoint is protected by `auth:sanctum` and uses `LessonJoinController`.

Current access behavior is already close to the target behavior:

- Assigned student can access the lesson join link during the join window.
- Assigned teacher can access the same lesson join link during the join window.
- Admin can access the lesson join link during the join window.
- Unassigned students and teachers receive a safe unauthorized response.
- Cancelled, rescheduled, missed, completed, and expired lessons do not expose meeting links.
- Too-early and expired join attempts do not expose meeting links.
- Join attempts are logged in `lesson_join_access_logs`.

The main missing piece is a dedicated `meeting_rooms` entity that owns provider room identifiers, lifecycle state, webhook correlation, and sensitive provider metadata separately from the lesson row.

## Whether Per-Lesson Rooms Are Supported

Yes, the application can support per-lesson rooms with the current domain model.

The `lessons` table already has the required scheduling and participant fields:

- `id`
- `student_id`
- `teacher_id`
- `start_time`
- `end_time`
- `status`

The current join access code already treats the lesson as the authorization boundary. A `meeting_rooms.lesson_id` foreign key with a unique constraint would naturally enforce one room per lesson and allow the teacher and student to resolve the same room.

Recommended cardinality:

- `Lesson hasOne MeetingRoom`
- `MeetingRoom belongsTo Lesson`

Use a unique index on `meeting_rooms.lesson_id`.

## Recommended Room Lifecycle

Recommended lifecycle states:

- `pending`
- `creating`
- `ready`
- `active`
- `ended`
- `cancelled`
- `expired`
- `failed`

Recommended flow:

1. Lesson is created as `scheduled` or `pending_confirmation`.
2. No provider room is required immediately unless notifications or calendar invites need the URL early.
3. When an authorized teacher/student/admin requests the join endpoint, Laravel checks lesson status, user authorization, and the join window.
4. If the lesson is eligible and no room exists, Laravel creates the room in a transaction or job-safe service.
5. The room is linked to the lesson through `meeting_rooms.lesson_id`.
6. Teacher and student receive role-appropriate join material for the same room.
7. If provider room creation is asynchronous, return a safe `creating` response or retry/poll until `ready`.
8. Provider webhooks update room status, active conference ID, participant events, and recording artifacts.
9. When the lesson is completed, cancelled, expired, missed, or rescheduled, Tutorvio stops returning join material.
10. Expiry cleanup marks stale rooms as `expired` and optionally ends active provider conferences when supported.

Creation timing recommendation:

- MVP: lazy-create on join, because it avoids unused rooms and stale links.
- Later: proactive creation when a lesson is confirmed if reminders, calendar invites, or pre-class checks require a room URL.

Reschedule behavior:

- Do not reuse the old room for the replacement lesson by default.
- Mark the old room `cancelled` or `expired`.
- Create a new room for the replacement lesson.
- The old lesson response may include replacement lesson metadata, but must not include the old or new join URL.

## Required DB Fields

Recommended table: `meeting_rooms`.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | bigint primary key | Internal Tutorvio room ID. |
| `lesson_id` | foreign ID, unique | Links exactly one room to one lesson. |
| `provider` | string | Example values: `google_meet`, `daily`, `custom`, `other`. |
| `provider_room_id` | string nullable | Stable provider resource ID, such as Google Meet `spaces/{space}`. |
| `provider_meeting_id` | string nullable | Provider meeting code/ID if distinct from room resource. |
| `room_name` | string nullable | Internal/provider room name. Should not be treated as secret auth. |
| `join_url` | string nullable | Join URL. Return only after Tutorvio authorization and only during allowed window. |
| `host_url` | text nullable encrypted | Host/admin URL if provider has one. Never expose to students. |
| `status` | string indexed | Room lifecycle status. |
| `starts_at` | timestamp nullable | Usually copied from lesson start time. |
| `ends_at` | timestamp nullable | Usually lesson end plus grace window. |
| `created_by` | foreign ID nullable | User who caused room creation, if applicable. |
| `metadata` | encrypted JSON nullable | Provider payload fragments, config, webhook correlation, non-public IDs. |
| `last_provider_sync_at` | timestamp nullable | Last successful provider reconciliation. |
| `created_at` | timestamp | Standard Laravel timestamp. |
| `updated_at` | timestamp | Standard Laravel timestamp. |

Recommended additional tables if participant/recording tracking is required:

- `meeting_room_events`
- `meeting_room_participants`
- `meeting_room_recordings`

Minimal `meeting_room_recordings` fields:

- `id`
- `meeting_room_id`
- `provider_recording_id`
- `provider_conference_id`
- `status`
- `started_at`
- `ended_at`
- `file_ready_at`
- `metadata` encrypted JSON
- `created_at`
- `updated_at`

Recommended indexes:

- Unique: `meeting_rooms.lesson_id`
- Unique or normal: `meeting_rooms.provider, provider_room_id`
- Normal: `meeting_rooms.provider, provider_meeting_id`
- Normal: `meeting_rooms.status, starts_at`
- Normal: `meeting_rooms.starts_at, ends_at`

## Metadata And Sensitive Data Rules

Provider API keys, OAuth secrets, SDK secrets, webhook signing secrets, and reusable admin credentials must not be stored in `meeting_rooms`.

Store provider credentials only in Laravel environment/config or a secrets manager.

Treat these fields as sensitive operational data:

- `provider_room_id`
- `provider_meeting_id`
- `room_name`
- `join_url`
- `host_url`
- `provider_recording_id`
- raw provider webhook payloads
- conference record IDs
- calendar event IDs

These values are not necessarily passwords, but they can leak meeting access, recording access, provider account structure, or correlation data. They should be omitted from general lesson resources and logs.

Recommended API exposure:

- General lesson/list/dashboard endpoints may expose `meeting_provider`, `is_join_available`, `join_starts_at`, and `join_ends_at`.
- Only the join endpoint should expose `join_url`.
- `host_url` should not be exposed to students. If a provider requires host privileges, generate short-lived role-scoped tokens instead of returning reusable host links.
- Encrypted JSON should be used for raw provider metadata when practical.
- Logs should store room IDs and access outcomes, not raw URLs, tokens, or provider payloads.

## Backend API Endpoints Needed

Keep and evolve the existing endpoint:

- `GET /api/v1/lessons/{lesson}/join`

Recommended behavior:

- Authorize the Tutorvio user.
- Check lesson status and join window.
- Create or fetch the lesson's meeting room.
- Return safe room metadata and `join_url` only when join is allowed.
- Never return provider room data for denied, early, expired, cancelled, completed, rescheduled, missed, or unauthorized access.

Optional admin/internal endpoints:

- `POST /api/v1/lessons/{lesson}/meeting-room`
  - Create or retry room creation manually.
- `GET /api/v1/lessons/{lesson}/meeting-room`
  - Admin/staff diagnostic view with sanitized room status.
- `PATCH /api/v1/lessons/{lesson}/meeting-room`
  - Admin/internal status correction if needed.
- `POST /api/v1/webhooks/meeting-providers/{provider}`
  - Provider webhook receiver for conference, participant, recording, transcript, or artifact events.

Recommended jobs/commands:

- Create rooms for upcoming confirmed lessons if proactive creation is enabled.
- Expire rooms after the lesson grace period.
- Retry failed room creation.
- Reconcile provider state after webhook outages.
- Pull recording/artifact status until ready.

## Provider Constraints

The repository currently defines these lesson meeting providers:

- `google_meet`
- `custom`
- `other`

There is no provider client implementation in the backend yet. Provider support below is therefore based on current provider documentation and should be revalidated during implementation.

### Google Meet

Google Meet can support per-lesson rooms, but it is a weaker fit for embedded Tutorvio classroom control than Daily/LiveKit-style programmable providers.

Supported:

- Scheduled rooms through Google Calendar conference data.
- Instant rooms through Google Meet REST API `spaces.create`.
- Persistent meeting spaces, with only one active conference in a space at one time.
- Participant event subscriptions through Google Workspace Events API.
- Recording event subscriptions through Google Workspace Events API.
- Recording and artifact retrieval through Google Meet REST API when artifacts are generated and permissions allow it.

Constraints:

- Google Meet spaces do not appear to support arbitrary Tutorvio metadata fields; store Tutorvio metadata locally.
- Meeting codes should not be stored as the only long-term identifier. Google notes that meeting codes can expire or become dissociated/reused. Store stable provider resource names such as `spaces/{space}` when available.
- Room expiry should be enforced by Tutorvio. Google Meet has `endActiveConference`, but no direct room delete/expiry lifecycle was found in the reviewed docs.
- Recording availability depends on Workspace policy, organizer permissions, and whether recording/artifacts are enabled.
- External/anonymous join behavior depends on Google Workspace settings.

Sources:

- Google Calendar conference data: <https://developers.google.cn/workspace/calendar/api/guides/create-events?hl=en>
- Google Meet REST API overview: <https://developers.google.com/workspace/meet/api/guides/overview>
- Google Meet spaces: <https://developers.google.cn/workspace/meet/api/guides/meeting-spaces?hl=en>
- Google Workspace Meet events: <https://developers.google.com/workspace/events/guides/events-meet>
- Google Meet artifacts: <https://developers.google.cn/workspace/meet/api/guides/artifacts?hl=en>

### Custom And Other

The current `custom` and `other` providers should be treated as manually supplied room URLs unless a provider adapter is added.

Recommended behavior for manual/custom providers:

- Store the link in `meeting_rooms.join_url`, not directly on `lessons`.
- Require the same Tutorvio authorization and join-window checks.
- Do not expose the link outside the join endpoint.
- Do not assume participant or recording webhooks exist.
- Mark provider capability flags explicitly in config or code.

## Provider Capability Checklist

| Capability | Google Meet | Custom/Other Current Repo State |
| --- | --- | --- |
| Scheduled rooms | Yes, through Calendar conference data | Only if manually supplied or adapter added |
| Instant rooms | Yes, through Meet `spaces.create` | Only if adapter added |
| Reusable rooms | Partially, via persistent spaces; one active conference at a time | Provider-specific |
| Expiring rooms | App-enforced; active conference can be ended | App-enforced |
| Room metadata | Limited provider metadata; store Tutorvio metadata locally | App-local only |
| Participant webhooks | Yes, through Workspace Events API | Provider-specific |
| Recording webhooks | Yes, through Workspace Events API | Provider-specific |
| Recording retrieval | Yes, with Meet artifacts and permissions | Provider-specific |
| Embed-friendly classroom UI | Weak | Provider-specific |

## Implementation Risks

### `lessons` Versus `lesson_records`

The app has both `lessons` and `lesson_records`, and both contain meeting-related fields. The active join endpoint uses `lessons`. The first implementation should link rooms to `lessons.id` unless product requirements explicitly move live class joining to `lesson_records`.

### Concurrency

Lazy creation can receive simultaneous requests from a teacher and student. Use a unique `lesson_id`, database transaction, and retry-safe service so only one provider room is created.

### Asynchronous Provider Creation

Google Calendar conference creation can be asynchronous. Other providers can also have delayed readiness. Room status must distinguish `creating`, `ready`, and `failed`.

### URL Leakage

Existing tests already verify meeting links are not exposed for denied states. The new room model must preserve that behavior. Do not add room URLs to dashboard, lesson list, or generic resource responses.

### Provider Identifier Stability

Do not rely only on human-friendly meeting codes. Store stable provider resource identifiers when available.

### Webhook Reliability

Provider webhooks can be delayed, duplicated, or missed. Store idempotency data where available and keep a reconciliation job for recordings and conference state.

### Recording Privacy

Recording IDs and export URLs can expose class content. Store recording metadata separately, authorize playback/download through Tutorvio, and avoid returning provider recording URLs directly unless the user is authorized.

### Reschedules

Reusing a room across rescheduled lessons can leak old participants, chat, recordings, or invite state. Default to a new room per replacement lesson.

### Provider Policy Dependencies

Google Meet recording, attendance, external access, and artifact behavior are affected by Workspace licensing and admin policy. These must be tested against the actual Tutorvio Workspace account before implementation is finalized.

## Recommended Implementation Direction

1. Add a `MeetingRoom` model and `meeting_rooms` migration.
2. Link `Lesson hasOne MeetingRoom`.
3. Move new room generation into a `MeetingRoomService`.
4. Keep `LessonJoinController` as the public join boundary.
5. Replace direct `lessons.meeting_link` usage with room lookup over time.
6. Preserve existing safe-denial behavior and access logging.
7. Add focused feature tests for:
   - room is created once per lesson;
   - assigned teacher and student receive the same room;
   - unauthorized users do not create or see rooms;
   - cancelled/completed/expired/rescheduled/missed lessons do not expose room URLs;
   - failed provider creation returns a safe response;
   - room metadata is not exposed in generic lesson responses.

## Conclusion

Tutorvio can support one automatically generated meeting room per lesson. The current authorization and join-window logic already provides the right security boundary, but meeting data should move from ad hoc lesson fields into a dedicated `meeting_rooms` model.

The safest backend design is one room per lesson, lazy-created through the join flow, guarded by existing lesson status and user authorization checks, with sensitive provider data stored server-side and exposed only through the authorized join endpoint.
