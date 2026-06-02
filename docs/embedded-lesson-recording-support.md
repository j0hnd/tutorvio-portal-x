# Embedded Lesson Recording Support

## Scope

This document investigates recording support for embedded Tutorvio lessons and proposes a backend data flow for linking provider recordings back to Tutorvio `lesson_id` records.

Current Tutorvio lesson meeting providers in the backend are:

- `google_meet`
- `custom`
- `other`

The repo currently has lesson meeting access fields (`meeting_link`, `meeting_provider`, `meeting_metadata`) and join access logging, but no dedicated lesson recording model.

## Recording Availability By Provider

| Provider | Recording support | Auto-start support | Manual start required | Storage location | Recording events/webhooks | Lesson linkage |
| --- | --- | --- | --- | --- | --- | --- |
| `google_meet` | Yes, when the organizer/account has an eligible Google Workspace, Google One, Workspace Individual, or education plan and admin recording is enabled. | Yes, for eligible Workspace configurations. Hosts can configure recording to start automatically, and Workspace admins can set automatic artifact defaults for supported editions. Auto recording still starts only after host/co-host joins on web. | Required when auto recording is unavailable or not configured. Host/co-host/eligible participant must start recording from Meet. | Google Drive, usually the meeting organizer's `Meet Recordings` folder. | Yes, through Google Workspace Events API delivered to Google Cloud Pub/Sub. Recording events include started, ended, and file generated. | Yes, if Tutorvio stores Google Meet space/conference identifiers in `meeting_metadata` and maps incoming recording events back to the lesson. |
| `custom` | Unknown. Depends on the selected embedded video provider. | Provider-dependent. | Provider-dependent. | Provider-dependent. | Provider-dependent. | Requires a provider-specific metadata contract. |
| `other` | Unknown. Depends on the provider. | Provider-dependent. | Provider-dependent. | Provider-dependent. | Provider-dependent. | Requires a provider-specific metadata contract. |

## Google Meet Findings

Google Meet supports recording, but it is not universally available for every Google account. Recording requires an eligible plan and must be enabled by the Google Workspace administrator when using Workspace-managed accounts.

Google Meet recordings are saved to the organizer's Google Drive and are subject to the organizer and organization Drive storage quotas. The recording link is emailed to the organizer and the person who started the recording. If the recording starts at the scheduled meeting time, the link can also be attached to the Google Calendar event.

Recording can be started manually from the meeting UI by an eligible host/co-host/user. Google also supports automatic recording configuration for eligible accounts and admin configurations, but automatic recording does not begin until the host or co-host joins on web.

The Google Meet REST API exposes recording artifacts. A recording resource includes state, a provider recording id/resource name, and a Drive destination. Once the recording file is generated, the Drive destination includes a Drive `fileId` and export URI. The states include:

- `STARTED`
- `ENDED`
- `FILE_GENERATED`

Google Meet recording events are available through the Google Workspace Events API. Events are delivered through Google Cloud Pub/Sub rather than direct provider-to-backend HTTP webhooks. Relevant event types include:

- `google.workspace.meet.recording.v2.started`
- `google.workspace.meet.recording.v2.ended`
- `google.workspace.meet.recording.v2.fileGenerated`

## Recording Flow Recommendation

1. Tutorvio creates or assigns the lesson room.
2. Backend stores provider meeting identifiers in `lessons.meeting_metadata`.
   - For Google Meet, store the meeting space name, conference record name when known, Calendar event id if applicable, and any provider room id.
3. Recording starts.
   - If provider supports automatic recording and Tutorvio has the required account/admin/OAuth setup, configure auto recording at room creation.
   - Otherwise, the teacher must manually start recording in the provider UI.
4. Provider emits recording lifecycle event.
   - For Google Meet, Workspace Events API publishes the event to Pub/Sub.
5. Backend receives the event through a Pub/Sub push endpoint or pull worker.
6. Backend validates the event source.
   - For Pub/Sub push, validate the OIDC JWT in the `Authorization` header.
   - Verify audience, issuer/signature, expected service account email, and `email_verified`.
   - Use Pub/Sub message id and provider event id for idempotency.
7. Backend resolves provider identifiers to `lesson_id`.
   - Prefer matching `conferenceRecord` or provider meeting space id stored in `meeting_metadata`.
   - Fall back to provider Calendar event id only if the room was created through Calendar and the mapping is reliable.
8. On recording started/ended, backend upserts a `lesson_recordings` row with status updates.
9. On recording file generated, backend calls the provider API to fetch full recording metadata.
10. Backend stores provider recording metadata and marks the recording as available.
11. Student, teacher, and admin access the recording through Tutorvio API based on lesson permissions.

## Webhook Requirements

Google Meet does not use a simple direct webhook model for recording-ready callbacks. The recommended integration requires:

- Google Cloud project.
- Google Workspace Events API enabled.
- Google Cloud Pub/Sub topic for Workspace events.
- Pub/Sub subscription to deliver messages to Tutorvio backend or a worker.
- OAuth credentials/scopes for Meet event and artifact access.
- A secure push endpoint or pull worker.
- OIDC JWT validation for authenticated Pub/Sub push delivery.
- Idempotent event processing.
- Retry handling for out-of-order or delayed events.

For Google Meet, the `fileGenerated` event should be treated as the recording-ready signal. Earlier `started` and `ended` events are useful for status, but the downloadable/playable file may not exist until `FILE_GENERATED`.

## Storage Options

### Option A: Provider-Hosted Recording

Store provider metadata and serve playback/download by redirecting or proxying to provider-hosted files.

Recommended for first implementation because it avoids immediate video storage and transcoding work.

Requirements:

- Store provider file ids, not only URLs.
- Refresh or regenerate access URLs when needed.
- Do not expose privileged provider metadata directly to clients.
- Ensure Drive sharing settings do not bypass Tutorvio authorization.

Risks:

- Organizer can delete or move the Drive file.
- Drive permissions can drift outside Tutorvio.
- Storage quota can prevent recording.
- URLs may change or become inaccessible.

### Option B: Tutorvio-Owned Storage

After provider file generation, backend downloads or copies the recording into Tutorvio-controlled object storage.

Recommended later if Tutorvio needs stronger retention, consistent access control, CDN delivery, or independence from provider Drive permissions.

Requirements:

- Object storage bucket.
- Background ingestion job.
- Retention policy.
- Signed playback/download URLs.
- Storage cost monitoring.
- Deletion and privacy workflows.

Risks:

- Higher storage and bandwidth cost.
- Longer processing path.
- More compliance responsibility.

## Access-Control Requirements

Recording access should mirror or tighten lesson access:

- Assigned student can view recordings for their own lesson.
- Assigned teacher can view recordings for their own lesson.
- Admin/staff access should require explicit permission, such as `lesson_recordings.view`.
- Recording endpoints should be protected by `auth:sanctum`.
- Backend should authorize every playback/download request.
- Frontend should never rely on hidden provider URLs for security.
- Raw provider metadata should not be returned unless required.
- Access should be logged, similar to `LessonJoinAccessLog`.

Recommended API behavior:

- `GET /api/v1/lessons/{lesson}/recordings` lists authorized recordings for the lesson.
- `GET /api/v1/lesson-recordings/{recording}` returns metadata only if authorized.
- `POST /api/v1/lesson-recordings/{recording}/playback-url` returns a short-lived Tutorvio-controlled URL or redirects after authorization.

## Proposed Recording Data Model

Table: `lesson_recordings`

| Field | Purpose |
| --- | --- |
| `id` | Primary key. |
| `lesson_id` | Foreign key to `lessons.id`. |
| `provider` | Provider key, for example `google_meet`. |
| `provider_recording_id` | Provider recording id/resource name. |
| `provider_conference_id` | Provider conference/room identifier used for event correlation. |
| `provider_file_id` | Provider storage file id, for example Google Drive file id. |
| `recording_url` | Provider playback/export URL or Tutorvio playback URL. |
| `download_url` | Provider download URL or Tutorvio download URL. |
| `duration_seconds` | Recording duration when available. |
| `status` | Recording lifecycle status. |
| `started_at` | When recording started. |
| `ended_at` | When recording ended. |
| `available_at` | When recording became available for playback/download. |
| `metadata` | JSON payload for provider-specific fields and event snapshots. |
| `created_at` | Laravel timestamp. |
| `updated_at` | Laravel timestamp. |

Suggested statuses:

- `started`
- `processing`
- `available`
- `failed`
- `deleted`

Suggested indexes:

- `lesson_id`
- Unique `provider`, `provider_recording_id`
- `provider`, `provider_conference_id`
- `status`
- `available_at`

Suggested relationships:

- `Lesson hasMany LessonRecording`
- `LessonRecording belongsTo Lesson`

## Risks, Costs, And Limitations

- Google Meet recording availability depends on account edition and admin settings.
- Google Meet automatic recording requires eligible Workspace features and may require Google Workspace Developer Preview or specific OAuth scopes for some space configuration APIs.
- Even when auto recording is configured, it starts only when the host/co-host joins on web.
- Google Meet recordings depend on organizer and organization Drive storage quota.
- Provider-hosted recordings can be deleted, moved, or permission-changed outside Tutorvio.
- Recording URLs should be treated as unstable. Store provider ids and regenerate access when serving.
- Recording consent/privacy requirements must be handled in product copy and policy. Participants are notified by Google Meet when recording starts/stops.
- Google Workspace Events API uses Pub/Sub, so implementation requires Google Cloud infrastructure and authenticated message validation.
- `custom` and `other` providers should be treated as unsupported for recording until the exact provider is selected and documented.

## Recommendation

Implement recording support behind a provider abstraction, but ship Google Meet first because it is the only current provider with identifiable recording APIs and events.

Use provider-hosted storage initially, storing provider ids and recording metadata in Tutorvio. Serve recordings only through authorized Tutorvio endpoints. Add Tutorvio-owned object storage later if retention, playback control, or provider permission drift becomes a product or compliance issue.

## Sources

- [Record a video meeting - Google Meet Help](https://support.google.com/meet/answer/9308681?hl=en-419)
- [Choose automatic meeting artifact settings - Google Workspace Admin Help](https://support.google.com/a/answer/15496523?hl=en)
- [Work with artifacts - Google Meet REST API](https://developers.google.com/workspace/meet/api/guides/artifacts)
- [Configure meeting spaces and members - Google Meet REST API](https://developers.google.com/workspace/meet/api/guides/meeting-spaces-configuration)
- [REST Resource: conferenceRecords.recordings - Google Meet API](https://developers.google.com/workspace/meet/api/reference/rest/v2/conferenceRecords.recordings)
- [Subscribe to Google Meet events - Google Workspace Events API](https://developers.google.com/workspace/events/guides/events-meet)
- [Authentication for Pub/Sub push subscriptions - Google Cloud](https://docs.cloud.google.com/pubsub/docs/authenticate-push-subscriptions)