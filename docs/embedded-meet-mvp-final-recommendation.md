# Tutorvio Embedded Meet MVP Final Technical Recommendation

Date: 2026-05-29

## Executive Recommendation

Use **Daily Prebuilt** for the embedded Tutorvio Meet MVP.

Recommended integration type: **hybrid iframe/prebuilt SDK**.

- The frontend embeds Daily Prebuilt inside a Tutorvio-owned classroom page.
- Laravel owns lesson authorization, room lifecycle, token minting, recording correlation, and audit logs.
- Daily owns real-time video/audio, screen sharing, in-call controls, in-call chat, participant list, and recording capture for MVP.
- Tutorvio owns lesson notes, lesson metadata, access control, recording access, and any durable classroom records.

Do not use Google Meet, Microsoft Teams, or raw Zoom meeting links for the MVP embedded classroom. They are better suited to external meeting redirects or enterprise tenant integrations, not a Tutorvio-owned embedded class experience.

## Recommended Provider

**Primary: Daily Prebuilt**

Daily is the best MVP fit because it supports:

- Embedded prebuilt meeting UI.
- REST room creation.
- REST meeting token creation.
- Private rooms.
- Room-scoped, user-scoped, short-lived meeting tokens.
- Teacher/student role differences through token properties.
- Recording support.
- Webhooks and meeting APIs for room, participant, and recording lifecycle handling.
- A future upgrade path to Daily custom call object without changing providers.

**Fallback: Whereby Embedded**

Whereby is the closest low-code alternative if Daily becomes commercially or technically blocked. It has strong embedded UI support and comparable participant-minute pricing, but its deeper SDK path is more React-oriented while Tutorvio is Vue.

**Future custom classroom path: LiveKit Cloud**

LiveKit is the strongest future option if Tutorvio wants full classroom UI ownership, custom chat/whiteboard primitives, advanced media events, and possible self-hosting later. It is not the fastest MVP path because Tutorvio would need to build the meeting shell and controls.

## Recommended Integration Type

Use **hybrid iframe/prebuilt SDK** for MVP.

Implementation shape:

1. Tutorvio page at `/lessons/{lesson}/classroom` or `https://meet.tutorvio.com/lessons/{lesson}`.
2. Vue calls Laravel to request join access.
3. Laravel returns a short-lived Daily room token and room URL only after authorization.
4. Vue embeds Daily Prebuilt using Daily's frontend library.
5. The embedded frame uses iframe permissions:
   - `camera`
   - `microphone`
   - `display-capture`
   - `fullscreen`
6. Laravel receives Daily webhooks and maps provider events back to Tutorvio lessons.

This is not a plain redirect, because users should stay in the Tutorvio classroom shell. It is not a fully custom SDK build, because Tutorvio should not own all WebRTC UI and device edge cases for MVP.

## Recommended Backend Data Model

Keep `lessons` as the scheduling and authorization boundary, but move provider room state into dedicated tables.

### Existing Fields To Keep During Migration

The current `lessons` table already has:

- `meeting_link`
- `meeting_provider`
- `meeting_metadata`
- `join_available_from`
- `join_available_until`

These can remain for backward compatibility, but MVP implementation should not depend on reusable `meeting_link` as the secure access mechanism.

### New Table: `meeting_rooms`

One row per lesson occurrence.

Recommended fields:

| Field | Purpose |
| --- | --- |
| `id` | Internal room ID. |
| `lesson_id` | Unique foreign key to `lessons.id`. |
| `provider` | `daily` for MVP. Add enum/support value. |
| `provider_room_id` | Daily room ID/name or stable provider identifier. |
| `provider_meeting_id` | Active meeting/session ID when known. |
| `room_name` | Provider room name. Not treated as an auth secret. |
| `join_url` | Provider room URL. Only returned after Tutorvio authorization. |
| `status` | `pending`, `creating`, `ready`, `active`, `ended`, `cancelled`, `expired`, `failed`. |
| `starts_at` | Lesson start or join-window start. |
| `ends_at` | Lesson end plus grace window. |
| `created_by` | User who caused lazy creation, nullable. |
| `metadata` | Encrypted JSON for provider config and correlation IDs. |
| `last_provider_sync_at` | Last successful provider reconciliation. |
| `created_at`, `updated_at` | Laravel timestamps. |

Indexes:

- Unique `lesson_id`.
- Unique or indexed `provider`, `provider_room_id`.
- Indexed `provider`, `provider_meeting_id`.
- Indexed `status`, `starts_at`.

Relationship:

- `Lesson hasOne MeetingRoom`
- `MeetingRoom belongsTo Lesson`

### New Table: `meeting_room_events`

Use this for idempotent webhook handling and later audit/replay.

Recommended fields:

- `id`
- `meeting_room_id`
- `provider`
- `provider_event_id`
- `event_type`
- `occurred_at`
- `payload` encrypted JSON
- `processed_at`
- `created_at`, `updated_at`

Indexes:

- Unique `provider`, `provider_event_id`.
- Indexed `meeting_room_id`, `occurred_at`.
- Indexed `event_type`, `occurred_at`.

### New Table: `meeting_room_participants`

Use this for attendance inputs, not as the final attendance authority.

Recommended fields:

- `id`
- `meeting_room_id`
- `lesson_id`
- `user_id` nullable
- `provider_participant_id`
- `role`
- `display_name`
- `joined_at`
- `left_at`
- `duration_seconds`
- `metadata` encrypted JSON
- `created_at`, `updated_at`

### New Table: `lesson_recordings`

Recordings should be linked directly to lessons and optionally to meeting rooms.

Recommended fields:

| Field | Purpose |
| --- | --- |
| `id` | Primary key. |
| `lesson_id` | Foreign key to `lessons.id`. |
| `meeting_room_id` | Foreign key to `meeting_rooms.id`, nullable for migration flexibility. |
| `provider` | `daily`. |
| `provider_recording_id` | Provider recording ID. |
| `provider_conference_id` | Daily meeting/session ID used for correlation. |
| `provider_file_id` | Provider storage/file ID if available. |
| `recording_url` | Provider playback URL or Tutorvio playback URL. Treat as protected. |
| `download_url` | Provider download URL or Tutorvio download URL. Treat as protected. |
| `duration_seconds` | Duration when available. |
| `status` | `started`, `processing`, `available`, `failed`, `deleted`. |
| `started_at` | Recording start time. |
| `ended_at` | Recording end time. |
| `available_at` | Playback/download ready time. |
| `metadata` | Encrypted provider metadata. |
| `created_at`, `updated_at` | Laravel timestamps. |

Indexes:

- `lesson_id`.
- `meeting_room_id`.
- Unique `provider`, `provider_recording_id`.
- Indexed `provider`, `provider_conference_id`.
- Indexed `status`, `available_at`.

## Recommended API Endpoints

Keep API routes versioned under `/api/v1` and protected by `auth:sanctum` where user access is involved.

### Student/Teacher Join Flow

`POST /api/v1/lessons/{lesson}/meeting/join`

Purpose:

- Authenticate Tutorvio user.
- Validate lesson access.
- Validate join window and lesson status.
- Lazy-create Daily room when needed.
- Mint short-lived Daily token.
- Log join attempt.
- Return only safe join material.

Recommended response:

```json
{
  "data": {
    "lesson_id": 123,
    "meeting_room_id": 456,
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

Denied responses must not include room URL, join URL, host URL, or provider token.

### Availability Endpoint

`GET /api/v1/lessons/{lesson}/join`

Recommended MVP behavior:

- Keep for backward compatibility and availability metadata.
- Do not mint provider tokens.
- Eventually stop returning reusable `meeting_link`.

### Recording Endpoints

`GET /api/v1/lessons/{lesson}/recordings`

- List recordings for an authorized lesson participant, teacher, or admin.

`GET /api/v1/lesson-recordings/{recording}`

- Return sanitized recording metadata after authorization.

`POST /api/v1/lesson-recordings/{recording}/playback-url`

- Return short-lived playback/download access after Tutorvio authorization.

### Webhook Endpoint

`POST /api/v1/webhooks/meeting-providers/daily`

Purpose:

- Verify provider webhook signature.
- Idempotently store webhook event.
- Resolve Daily room/session/recording IDs to `meeting_rooms` and `lessons`.
- Update room, participant, attendance-input, and recording rows.

### Admin/Internal Endpoints

These are not required for the first user-facing release but are useful operationally:

- `GET /api/v1/lessons/{lesson}/meeting-room`
- `POST /api/v1/lessons/{lesson}/meeting-room`
- `POST /api/v1/meeting-rooms/{meetingRoom}/sync`

Protect with admin/staff permissions.

## Recommended Authentication Flow

Tutorvio remains the identity and authorization source of truth.

Flow:

1. User logs into Tutorvio and receives a Sanctum-authenticated API session/token.
2. User opens the lesson classroom.
3. Frontend calls `POST /api/v1/lessons/{lesson}/meeting/join`.
4. Laravel validates:
   - `auth:sanctum`
   - active user
   - assigned student, assigned teacher, or authorized admin/staff
   - lesson status is `scheduled` or `pending_confirmation`
   - current time is inside join window
   - lesson is not cancelled, completed, expired, missed, or rescheduled
5. Laravel creates or fetches the Daily room.
6. Laravel creates a Daily meeting token scoped to:
   - one room
   - one Tutorvio user
   - one role
   - short expiry
7. Frontend embeds Daily Prebuilt with the returned room URL and token.

Recommended token rules:

- Always set `room_name`.
- Set `user_id` to a stable Tutorvio user identifier.
- Set `user_name` from Tutorvio profile data.
- Set `is_owner: true` only for assigned teacher and explicitly authorized admin/staff.
- Student tokens are non-owner.
- Token expiry should be the earlier of `now + 10 minutes` or `join_available_until`.
- Enable ejection at token expiry where supported.
- Recording controls should be teacher/admin only.
- Do not log raw provider tokens or signed URLs.

Guest access should be disabled for normal Tutorvio lessons.

## Recommended Recording Flow

Use **provider-hosted Daily cloud recording** for MVP, then optionally copy recordings to Tutorvio-owned object storage later.

MVP flow:

1. Teacher joins with an owner token.
2. Recording is available only to teacher/admin role.
3. Recording starts manually from the Daily Prebuilt UI, or later by room/token policy if product requires auto-recording.
4. Daily emits recording/session webhook events.
5. Laravel verifies the webhook signature.
6. Laravel stores the event in `meeting_room_events`.
7. Laravel resolves Daily room/session/recording identifiers to `meeting_rooms.lesson_id`.
8. Laravel upserts `lesson_recordings`.
9. When recording is available, Laravel stores provider recording metadata and marks the recording `available`.
10. Users request playback through Tutorvio recording endpoints.
11. Laravel authorizes the user against lesson permissions before returning a short-lived provider playback/download URL or Tutorvio proxy URL.

MVP recording constraints:

- Recording URLs are protected data, not public lesson fields.
- Recording access should be logged.
- Product must show clear recording consent/notification rules.
- Provider-hosted recordings can be affected by provider retention, account settings, or deletion. Tutorvio-owned storage is future scope.

## Recommended Notes Flow

Lesson notes should stay in Tutorvio's existing lesson module.

MVP flow:

1. Teacher joins and teaches through embedded Daily.
2. Teacher writes notes in Tutorvio UI, not inside Daily chat.
3. Notes are saved using the existing `lesson-notes` API/resource model.
4. On lesson completion, existing lesson-note requirements and review flows remain the source of truth.
5. Admin oversight continues through Tutorvio's lesson-note review permissions.

Do not use provider chat, captions, transcript, or recording summaries as Tutorvio lesson notes in MVP.

Future enhancement:

- Link a recording transcript or AI summary to the lesson as an assistive draft, but require teacher review before it becomes an official lesson note.

## Recommended Chat And Whiteboard Approach

### Chat

MVP:

- Use Daily Prebuilt native in-call chat for ephemeral, in-session communication.
- Do not promise persistent Tutorvio chat history or downloadable chat transcript.
- Do not use Daily chat as the official lesson record.

Future:

- Build Tutorvio-owned classroom chat if durable history, moderation, export, parent/admin review, or compliance retention is required.
- Persist chat messages in Tutorvio with `lesson_id`, `meeting_room_id`, `sender_id`, message body, timestamps, and moderation metadata.

### Whiteboard

MVP:

- Do not include a whiteboard unless product explicitly accepts a separate lightweight Tutorvio panel.
- If included, make it a Tutorvio-owned module, not a provider-native whiteboard dependency.

Recommended future options:

- Build with tldraw, Excalidraw, or another collaborative canvas library.
- Use Tutorvio WebSockets or provider data channels for live sync.
- Store whiteboard snapshots/artifacts against `lesson_id`.

## Should `meet.tutorvio.com` Be Used?

Yes, but only as Tutorvio's classroom shell domain.

Recommended MVP domain strategy:

- Use `https://meet.tutorvio.com` for the live classroom page.
- Route examples:
  - `https://meet.tutorvio.com/lessons/{lesson}`
  - `https://meet.tutorvio.com/lessons/{lesson}/join`
- Authenticate through Tutorvio/Laravel before provider access is exposed.
- Embed Daily Prebuilt inside that page.
- Keep Daily's actual room URL under the Daily account domain, such as `https://tutorvio.daily.co/{room}`.

Do not assume `meet.tutorvio.com` can become the canonical Daily room hostname. For Daily MVP, it is the branded wrapper and access-control entry point.

Required setup:

- DNS and SSL for `meet.tutorvio.com`.
- Sanctum/CORS/session configuration for the classroom host.
- CSP allowing Daily frame/script/connect/media endpoints.
- Iframe permission policy for camera, microphone, screen share, and fullscreen.
- Mobile fallback that opens the authorized provider experience in a new tab when embedded mobile behavior is poor.

## MVP Scope

Ship only the pieces needed for secure, embedded, lesson-linked live classes.

Included:

- Add `daily` as a supported provider.
- Configure Daily API key server-side.
- Create `meeting_rooms`.
- Create `lesson_recordings`.
- Create `meeting_room_events`.
- Lazy-create one Daily room per lesson occurrence.
- Link room to `lesson_id`.
- `POST /api/v1/lessons/{lesson}/meeting/join`.
- Short-lived Daily meeting tokens.
- Teacher/student role mapping.
- Embedded Daily Prebuilt in a Tutorvio classroom page.
- Join availability and denied-state UI.
- Join attempt logging without provider token leakage.
- Daily webhook receiver with signature verification and idempotency.
- Recording metadata linked back to `lesson_id`.
- Recording list/playback endpoints protected by Tutorvio authorization.
- Lesson notes remain in Tutorvio's existing lesson-note module.
- Basic operational docs for Daily configuration, CSP, and webhook setup.

## Non-MVP / Future Scope

Defer:

- Fully custom WebRTC classroom UI.
- LiveKit migration or self-hosted media stack.
- Tutorvio-owned persistent classroom chat.
- Downloadable chat transcripts.
- Tutorvio-owned collaborative whiteboard.
- Provider-independent classroom abstraction for several providers at once.
- Automatic recording policies beyond a simple teacher/admin-controlled MVP.
- Tutorvio-owned recording storage, transcoding, CDN, retention, and deletion workflows.
- AI recording summaries, transcript-based note drafts, or student performance analytics.
- Breakout rooms.
- Parent observer mode.
- Advanced admin live monitoring dashboard.
- Calendar invite integration with provider join URLs.
- Mobile app SDK integration.

## Known Risks

- **Provider UI limits:** Daily Prebuilt is not a fully Tutorvio-designed classroom.
- **Mobile embed behavior:** iOS and small screens may need a redirect/new-tab fallback.
- **Screen sharing limits:** Desktop is strongest; mobile screen share is limited across providers.
- **Recording compliance:** Consent, notification, retention, and access policies must be defined before broad rollout.
- **Provider-hosted recordings:** Provider retention, availability, deletion, and URL behavior may not match Tutorvio retention needs.
- **Webhook reliability:** Webhooks can be delayed, duplicated, or missed; store events idempotently and add reconciliation jobs.
- **Reusable URLs:** Any direct provider room URL can leak. Access must depend on short-lived tokens and private rooms.
- **Token leakage:** Avoid query-string token exposure where possible and never log raw tokens.
- **CSP and permissions:** Incorrect frame/connect/media policies can break camera, mic, screen share, or recording.
- **Data ownership:** Provider-native chat should not be treated as Tutorvio-owned history.
- **Provider lock-in:** Room/token/recording semantics will be Daily-specific in MVP.
- **Cost growth:** Participant-minute and recording-minute costs scale directly with usage.

## Expected Provider Costs Or Plan Requirements

Pricing should be rechecked before contract/signoff because provider pricing changes.

Current checked pricing, 2026-05-29:

### Daily

Daily's public Video SDK pricing lists:

- 10,000 free participant minutes per month.
- Video/audio calls at $0.004 per participant-minute after free monthly minutes, with graduated volume discounts.
- Audio-only calls at $0.00099 per participant-minute after free monthly minutes.
- Cloud recording at $0.01349 per recorded minute.
- Additional recording storage at $0.003 per minute.
- Realtime transcription at $0.0059 per unmuted participant-minute.
- Post-call transcription at $0.0043 per recorded minute.
- HIPAA/BAA add-on at $500/month if needed.
- Premium support starts at $1,500/month; enterprise support starts at $5,000/month.

MVP cost example:

- 1 teacher + 1 student.
- 60 minute lesson.
- 120 participant minutes.
- Daily video cost after free tier: `120 * $0.004 = $0.48`.
- If recorded for 60 minutes: `60 * $0.01349 = $0.8094`.
- Approximate provider cost after free tier: `$1.29` before storage, transcription, support, and taxes.

### Whereby Comparison

Whereby Embedded public pricing lists:

- Explore plan: free with 2,000 participant minutes/month and limited features.
- Build plan: $9.99/month.
- 2,000 included participant minutes.
- Additional participant minutes at $0.004.
- Cloud recording at $0.01 per minute.
- Recording transcription at $0.024 per minute.
- HIPAA compliance add-on at $16.99/month.

### LiveKit Future Comparison

LiveKit Cloud public pricing lists:

- Build: $0/month.
- Ship: $50/month.
- Scale: $500/month.
- WebRTC minutes included by plan, then overage on paid tiers.
- Recording/export transcode minutes billed separately after included amounts.

LiveKit may become cheaper at scale or more controllable for a custom classroom, but the engineering cost is materially higher for MVP.

## Implementation Phases

### Phase 0: Product And Compliance Decisions

- Confirm recording policy: manual versus automatic, consent copy, retention, and who can view recordings.
- Confirm whether provider-native chat is acceptable as ephemeral only.
- Confirm whether whiteboard is excluded from MVP.
- Confirm whether `meet.tutorvio.com` is required for first release or can follow immediately after core integration.

### Phase 1: Backend Foundation

- Add `daily` to meeting provider support.
- Add config entries for Daily API key, domain, webhook secret, default room settings, and join-token TTL.
- Add migrations/models for:
  - `meeting_rooms`
  - `meeting_room_events`
  - `meeting_room_participants`
  - `lesson_recordings`
- Add provider interface:
  - `createOrFetchRoom(Lesson $lesson)`
  - `createJoinToken(Lesson $lesson, User $user, MeetingRole $role)`
  - `verifyWebhook(Request $request)`
  - `handleWebhook(array $payload)`
- Add `DailyMeetingProvider`.

### Phase 2: Join API And Security

- Add `POST /api/v1/lessons/{lesson}/meeting/join`.
- Reuse and harden existing lesson access checks.
- Keep current join access logging, but do not log provider tokens or full signed URLs.
- Lazy-create Daily room if missing.
- Mint short-lived room-scoped token.
- Return safe embed payload.
- Add focused feature tests for:
  - assigned teacher can join
  - assigned student can join
  - unassigned user denied
  - cancelled/rescheduled/completed/expired lesson denied
  - outside join window denied
  - denied responses contain no room URL/token
  - teacher receives owner capability and student does not

### Phase 3: Frontend Classroom MVP

- Add classroom route/page.
- Call join endpoint on user action.
- Show safe waiting/denied states from availability metadata.
- Embed Daily Prebuilt.
- Configure iframe permissions.
- Add fallback button for mobile/new-tab join after authorization.
- Keep lesson notes UI as Tutorvio-native.

### Phase 4: Webhooks And Recordings

- Add Daily webhook endpoint.
- Verify signatures.
- Store provider events idempotently.
- Update room status and participant records from meeting events.
- Link recording events to `lesson_recordings`.
- Add recording list and playback-url endpoints.
- Add focused tests for webhook verification, idempotency, and recording authorization.

### Phase 5: Domain And Hardening

- Configure `meet.tutorvio.com`.
- Add CORS/Sanctum/CSP settings.
- Validate camera, mic, screen share, recording, and exit behavior on:
  - Chrome desktop
  - Safari desktop
  - Edge desktop
  - iOS Safari
  - Android Chrome
- Add reconciliation job for missed webhook events.
- Add cleanup job for expired rooms.
- Add operational runbook for Daily dashboard/API configuration.

### Phase 6: Post-MVP Evaluation

- Measure usage cost, join success, recording success, browser issues, and support tickets.
- Decide whether to:
  - stay on Daily Prebuilt,
  - move to Daily custom call object,
  - build Tutorvio chat/whiteboard,
  - or evaluate LiveKit for a fully custom classroom.

## Source Notes

This recommendation synthesizes the local investigation docs:

- `docs/embedded-meet-integration-report.md`
- `docs/classroom-feature-support.md`
- `docs/lesson-meeting-room-generation.md`
- `docs/meeting-auth-session-security.md`
- `docs/embedded-lesson-recording-support.md`
- `docs/meet-tutorvio-domain-feasibility.md`

Current provider references checked on 2026-05-29:

- Daily pricing: https://www.daily.co/pricing/video-sdk/
- Daily API overview: https://docs.daily.co/
- Daily meeting tokens API: https://docs.daily.co/reference/rest-api/meeting-tokens/create-meeting-token
- Whereby Embedded pricing: https://whereby.com/information/embedded/pricing
- LiveKit pricing: https://livekit.com/pricing
- LiveKit Cloud overview: https://docs.livekit.io/intro/cloud/
