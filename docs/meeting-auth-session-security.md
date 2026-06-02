# Meeting Authentication and Session Security

Date: 2026-05-29

## Scope

This document defines how authenticated Tutorvio users should securely join embedded lesson meeting rooms. It is a design/security investigation only; no implementation is included.

The recommended MVP provider remains Daily Prebuilt, based on `docs/embedded-meet-integration-report.md`. The same backend-first security model should be kept if Tutorvio later chooses LiveKit, Twilio, Vonage, Zoom Video SDK, or another programmable provider.

Daily references:

- Daily creates rooms through the REST API using a server-side bearer API key: <https://docs.daily.co/reference/rest-api/rooms/create-room>
- Daily creates meeting tokens through the REST API: <https://docs.daily.co/reference/rest-api/meeting-tokens/create-meeting-token>
- Daily recommends setting `room_name` on tokens used for access control and setting `exp` on meeting tokens.

## Required Auth Model

Tutorvio remains the system of record for identity, lesson access, and role. Provider identity is derived from the authenticated Tutorvio user only after Laravel authorizes the join request.

Current repository behavior already supports the base model:

- `/api/v1/lessons/{lesson}/join` is behind `auth:sanctum`.
- Tutorvio login issues expiring Sanctum bearer tokens, currently defaulting to `SANCTUM_EXPIRATION` / 120 minutes.
- `Lesson::userCanAccessMeeting()` allows the assigned student, assigned teacher, and admins.
- `Lesson::joinAvailability()` applies lesson status and the configured join window.
- `lesson_join_access_logs` records allowed, denied, early, expired, cancelled, and rescheduled attempts.

The meeting provider must not become an auth authority for Tutorvio. A valid provider room URL or token is not enough; every join must start with a valid Tutorvio API session.

## Provider Credential Model

For Daily, the backend requires:

- Daily API key stored only in Laravel environment/config.
- Daily domain/room metadata stored server-side.
- Daily room creation through Laravel.
- Daily meeting token creation through Laravel.

The frontend must never receive:

- Daily API key.
- Provider OAuth client secret.
- Provider SDK secret.
- Provider room administration API credentials.
- Host/admin URL that can be reused outside Tutorvio authorization.

The frontend may receive only the minimum join material needed for the current authorized session, such as:

- Provider name.
- Room URL or embed URL.
- Short-lived provider meeting token, or a signed join URL containing that token.
- Non-sensitive UI metadata such as `lesson_id`, join window, and user role label.

## Token Generation Behavior

Daily does not require Tutorvio users to perform OAuth. Laravel should call Daily with the server-side API key to create/fetch a private room and generate a meeting token.

Recommended token properties:

- `room_name`: required for access-control tokens so the token is valid for one room only.
- `user_id`: Tutorvio user ID or a stable namespaced value such as `tutorvio:user:{id}`.
- `user_name`: display name from Tutorvio.
- `is_owner`: `true` only for assigned teacher and explicit admin observer/moderator joins.
- `nbf`: no earlier than the lesson join window start.
- `exp`: short-lived expiry, no later than the lesson join window end.
- `eject_at_token_exp`: `true`, so the provider removes users when the token expires.
- Permissions: role-specific camera, microphone, screen share, chat, and recording controls.

Tutorvio should create provider rooms as private rooms. Public rooms should not be used for lesson access because possession of a URL can become enough to join or request admission.

## Token Expiry Recommendation

Use two layers of time limits:

- Room limit: room `nbf`/`exp` should cover the scheduled lesson plus a small operational buffer.
- Join token limit: participant token should be short-lived and scoped to the current request.

Recommended values:

- Token `nbf`: lesson `join_available_from`.
- Token `exp`: the earlier of `now + 10 minutes` or `join_available_until`.
- Token ejection: enabled at token expiry.
- Room `exp`: `join_available_until` plus at most 15-30 minutes if provider cleanup/webhook timing needs tolerance.

Short-lived tokens reduce damage if a token appears in browser history, logs, screenshots, or support tickets. Users can request a fresh token while still authorized and inside the join window.

## Role Mapping

Tutorvio roles should map to provider session capabilities, not provider account identities.

| Tutorvio role | Provider role | Meeting capabilities |
| --- | --- | --- |
| Assigned teacher | Owner/moderator | Start/manage session, publish audio/video, screen share, manage participants, optionally record. |
| Assigned student | Participant | Join assigned lesson, publish audio/video according to class policy, limited/no moderation. |
| Admin/staff observer | Owner/moderator or observer | Join only for explicit operational need; can observe/moderate based on permission. |
| Unassigned user | None | No room URL, no token, safe error only. |

For Daily, teacher/admin owner behavior maps to `is_owner: true`; students should receive non-owner tokens with narrower permissions.

Recording control should be teacher/admin only. If recordings are enabled, recording artifact IDs and access permissions must be stored and checked in Tutorvio before playback/download.

## Access-Control Rules

Laravel must enforce all of these before creating or returning join material:

- Request is authenticated with `auth:sanctum`.
- User status is active.
- Lesson exists and is not cancelled, rescheduled, completed, expired, or missed.
- User is the lesson's assigned student, assigned teacher, or an admin/staff user with explicit meeting oversight permission.
- Current time is inside the lesson join window.
- Lesson has valid start/end times and provider configuration.
- Replacement lessons do not leak old or new meeting links from the old lesson response.
- A rescheduled lesson requires joining through the replacement lesson ID after separate authorization.
- Rate limiting should apply to join-token minting to reduce token spam and abuse.
- All join attempts should be logged without storing raw provider tokens.

The dashboard should not expose direct provider links. It can expose `is_join_available`, `join_starts_at`, and `join_ends_at`, then direct the frontend to call the join endpoint when the user clicks Join.

## Guest Access

Guest access should be disabled for Tutorvio lessons.

For Daily:

- Use private rooms.
- Require meeting tokens for entry.
- Do not rely on public room URLs.
- Avoid knock/admit flows for ordinary lessons unless Tutorvio can bind the admitted participant back to an authorized Tutorvio user.

If a future provider supports anonymous waiting rooms, lobby admission, or host approval, those features should be treated as secondary UX only. They must not replace Tutorvio authorization.

## Room/Lesson Scoping

Each provider room should map to one lesson occurrence unless there is a strong operational reason to reuse rooms. One lesson occurrence per room is safer because it prevents cross-lesson leakage, stale participants, and accidental reuse of recordings/chat.

Store these server-side fields:

- `provider`
- `provider_room_id` or room name
- `provider_room_url`
- room `nbf`/`exp`
- provider configuration version
- recording artifact IDs
- webhook/session event IDs

Do not store provider API keys, long-lived host links, reusable admin URLs, or raw participant tokens in `meeting_metadata`.

## Secure Join Flow

1. Frontend calls `POST /api/v1/lessons/{lesson}/meeting/join` or `POST /api/v1/lessons/{lesson}/join-token` with the Tutorvio bearer token.
2. Laravel authenticates through Sanctum.
3. Laravel loads the lesson and validates user, role, lesson status, and join window.
4. Laravel creates or fetches a private provider room for this lesson occurrence.
5. Laravel generates a short-lived, room-scoped provider token or signed join URL with role-specific permissions.
6. Laravel logs the attempt with outcome and reason, but not the raw token.
7. Frontend opens the embedded provider UI using iframe/SDK and the returned token or signed URL.
8. Unauthorized, too-early, expired, cancelled, and rescheduled joins receive safe responses without provider URL/token leakage.

Recommended response shape for allowed joins:

```json
{
  "data": {
    "lesson_id": 123,
    "provider": "daily",
    "room_url": "https://tutorvio.daily.co/lesson_123_...",
    "join_token": "short-lived-provider-token",
    "expires_at": "2026-06-01T09:00:00Z",
    "role": "teacher",
    "embed": {
      "mode": "iframe",
      "allow": "camera; microphone; display-capture; fullscreen"
    }
  }
}
```

Recommended safe denied response:

```json
{
  "message": "Unauthorized.",
  "reason": "unauthorized"
}
```

For expected timing/state denials, `200` with safe availability metadata is acceptable, matching the current endpoint pattern, as long as no provider URL/token is returned.

## Backend Endpoint Recommendations

Prefer replacing reusable link delivery with a token-minting endpoint:

- `POST /api/v1/lessons/{lesson}/meeting/join`
- or `POST /api/v1/lessons/{lesson}/join-token`

Use `POST` because the endpoint mints credentials and writes an access log. Keep the current `GET /api/v1/lessons/{lesson}/join` only for availability metadata, or migrate it to return no direct meeting URL.

Recommended backend components:

- `MeetingProvider` interface for `createOrFetchRoom(Lesson $lesson)` and `createJoinToken(Lesson $lesson, User $user, MeetingRole $role)`.
- `DailyMeetingProvider` implementation that keeps the Daily API key server-side.
- `LessonMeetingService` that owns Tutorvio validation, room lifecycle, role mapping, token expiry, and logging.
- Feature tests for allowed teacher/student/admin joins, unassigned denial, outside-window denial, cancelled/rescheduled denial, token expiry, and no secret leakage.

## Security Risks

- Reusable provider links can be forwarded and joined by unauthorized users.
- Public rooms or guest/knock access can bypass Tutorvio role checks.
- Tokens without room scoping may allow access to every room in the provider domain.
- Tokens without expiry remain useful after lesson end.
- Frontend provider secrets expose room creation, recording, and administration APIs.
- Host/admin URLs in the database or API responses can grant elevated permissions.
- Query-string tokens can leak through browser history, referrers, logs, analytics, screenshots, and support tooling.
- Dashboard or lesson-list APIs can accidentally expose join URLs before the user clicks Join.
- Webhook payloads and meeting metadata can leak provider participant IDs or recording URLs if returned directly.
- Admin observer access can become a privacy issue unless permissioned and audited.

## Backend Security Requirements

- Store provider credentials only in backend environment/config.
- Mint provider access only after Tutorvio authorization.
- Use private, per-lesson rooms.
- Scope every participant token to one room and one Tutorvio user.
- Give every token `nbf`, `exp`, and ejection behavior where supported.
- Keep teacher/admin moderation separate from student participation.
- Disable guest/public access for normal lessons.
- Never return provider secrets or long-lived admin links to the frontend.
- Never log raw provider tokens or signed URLs.
- Preserve and expand `lesson_join_access_logs` for auditability.
- Treat recording URLs as protected resources requiring separate Tutorvio authorization.
- Use HTTPS and iframe permissions for camera, microphone, screen share, and fullscreen.
- Add provider webhooks only with signature verification and idempotent event handling.

