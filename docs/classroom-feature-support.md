# Classroom Feature Support For Embedded Tutorvio Meet

Date: 2026-05-29

## Scope

This document investigates classroom feature support for embedded Tutorvio Meet. It does not implement code.

The current backend provider enum is still limited to:

- `google_meet`
- `custom`
- `other`

Prior Tutorvio meeting research recommends **Daily Prebuilt** as the MVP embedded provider, with **Whereby Embedded** as the closest low-code alternative and **LiveKit** as the strongest custom classroom path. Google Meet is included because it exists in the current data model, but it remains a weak fit for an embedded Tutorvio-owned classroom.

## Feature Matrix

Legend:

- Native: provider feature exists in the provider meeting UI or SDK.
- Build in Tutorvio: Tutorvio should own the data/UI even if provider transport can help.
- Separate service: needs a dedicated integration outside the video provider.
- Limited: available with important embed, API, license, or data ownership constraints.

| Feature | Daily Prebuilt | Whereby Embedded | Google Meet | LiveKit Custom |
| --- | --- | --- | --- | --- |
| In-meeting chat | Native | Native | Native in Meet UI | Build in Tutorvio using LiveKit text/data |
| Downloadable chat transcript | Limited; not a reliable Tutorvio-owned class record unless recording/chat outputs are explicitly enabled and retrieved | Not suitable for durable records; chat/files are session-oriented | Limited; Meet REST exposes meeting transcripts, not a clean Meet chat export API for Tutorvio classroom chat | Build in Tutorvio |
| Persistent chat history | Build in Tutorvio | Build in Tutorvio | Limited/provider-owned; not suitable as Tutorvio-owned class history | Build in Tutorvio |
| Whiteboard | Separate service | Limited/native via integrations such as Miro; separate service if Tutorvio needs ownership | Native in Meet for users, but provider-owned and not embedded/control-friendly | Separate service or Tutorvio-owned whiteboard |
| Screen sharing | Native | Native | Native | Native SDK capability; Tutorvio builds UI |
| File sharing | Build in Tutorvio; Daily Prebuilt does not appear to be a classroom file repository | Native session file sharing through chat, but temporary | Native/provider-owned through Meet/Workspace patterns, not Tutorvio-owned | Build in Tutorvio or use LiveKit byte streams for transport plus Tutorvio storage |
| Hand raise | Native | Native in prebuilt UI | Native | Build in Tutorvio using participant attributes/data |
| Participant list | Native | Native | Native | Build in Tutorvio from room participants |
| Teacher controls | Native owner controls, configurable by token/room | Native host controls in prebuilt UI | Native host/co-host controls, provider-owned | Build in Tutorvio using LiveKit permissions and server APIs |
| Mute/remove participant | Native owner/admin controls | Native host controls in prebuilt UI | Native host/co-host controls, limited external control | Native backend APIs exist; Tutorvio builds teacher UI |
| Attendance tracking | Use provider join/leave webhooks plus Tutorvio records | Use web component events/webhooks plus Tutorvio records | Use Workspace Events / Meet participant sessions plus Tutorvio records | Use LiveKit webhooks plus Tutorvio records |
| Join/leave events | Native webhooks and SDK events | Native web component events/webhooks | Native Workspace Events / REST participant sessions | Native webhooks/client events |
| Lesson notes | Build in Tutorvio | Build in Tutorvio | Build in Tutorvio | Build in Tutorvio |
| Custom UI events | Limited; Daily supports SDK events, app messages, and custom buttons, but Prebuilt UI is still provider UI | Limited; web component events/commands, deeper custom path is React SDK | Weak for Tutorvio-owned embedded UI | Native/custom; Tutorvio owns event model |

## Provider Findings

### Daily Prebuilt

Daily Prebuilt is the best MVP fit for an embedded classroom. It supports embedded video UI, text chat, screen sharing, hand raising, participant list, recording, and configurable room/token behavior. Daily also exposes client-side participant events and REST webhooks for meeting start/end and participant join/leave, which are enough for attendance foundations.

Provider-native:

- Video/audio meeting UI.
- In-meeting text chat.
- Screen sharing.
- Hand raise.
- Participant list.
- Owner/moderator behavior through room/token settings.
- Join/leave events through SDK and webhooks.

Build in Tutorvio:

- Persistent chat history, if Tutorvio needs durable class chat.
- Downloadable chat transcript as a Tutorvio-owned artifact.
- Lesson notes.
- Lesson materials/file sharing.
- Attendance records, using Daily events as inputs rather than the source of truth.
- Classroom-specific UI events and audit events.

Separate service:

- Whiteboard, unless Tutorvio builds its own canvas/collaboration tool.

Risks:

- Provider-native chat may remain inside Daily Prebuilt and should not be treated as a reliable Tutorvio database record without an explicit capture/export design.
- Prebuilt UI limits full classroom branding and layout control.
- Mobile embedded classroom UX needs validation, especially iOS.

### Whereby Embedded

Whereby is the strongest low-code alternative to Daily. Its Web Component provides prebuilt embedded UI, events, and commands. It has strong session features, including chat, screen sharing, raise hand, participant list, host controls, and temporary file sharing.

Provider-native:

- Video/audio meeting UI.
- In-meeting chat.
- Session file sharing through chat.
- Screen sharing.
- Raise hand.
- Participant list.
- Host controls, including remote mic/camera off, remove participant, room lock, and end meeting in prebuilt UI.
- Join/leave style events through the embed element and webhooks.

Build in Tutorvio:

- Persistent chat history.
- Downloadable class chat transcript.
- Lesson notes.
- Durable lesson materials/file storage.
- Attendance records.
- Tutorvio-specific classroom events.

Separate service:

- Whiteboard if Tutorvio needs ownership. Whereby advertises Miro-style integrations, but that is not the same as Tutorvio-owned board data.

Risks:

- Whereby file sharing is intentionally temporary; files are deleted shortly after the session and should not be used for lesson materials.
- The deeper custom SDK path is React-oriented, while Tutorvio frontend is Vue.
- Durable chat/transcript behavior should be assumed Tutorvio-owned unless Whereby confirms an exportable session chat artifact that meets retention needs.

### Google Meet

Google Meet is already represented in the current backend model, but it is not a good embedded Tutorvio classroom provider. It is strongest as a scheduled/deep-linked external meeting product with Workspace artifacts and events.

Provider-native:

- Meet chat inside Google Meet.
- Screen sharing.
- Participant list.
- Host/co-host controls.
- Whiteboard/collaboration features for eligible Google Workspace usage.
- Meeting transcripts and smart notes as Meet artifacts, depending on account, policy, and configuration.
- Participant join/leave events through Google Workspace Events API or REST participant sessions.

Build in Tutorvio:

- Lesson notes.
- Tutorvio attendance records from Meet participant sessions/events.
- Persistent Tutorvio classroom chat, if required.
- File/material management.
- Custom classroom UI events.

Separate service:

- Tutorvio-owned embeddable whiteboard.

Risks:

- Google Meet is not a normal embeddable video widget inside Tutorvio; users are effectively in Google Meet.
- Chat is provider-owned and not clearly exposed as a durable Tutorvio chat transcript artifact.
- Artifact access depends on Workspace edition, admin policies, organizer privileges, OAuth scopes, and Google Drive storage.
- Workspace Events API uses Google Cloud Pub/Sub, adding infrastructure complexity.

### LiveKit Custom

LiveKit is the strongest path if Tutorvio wants to own the classroom UI and product behavior. It is not a low-code embedded provider; Tutorvio must build the classroom shell.

Provider-native/platform-native:

- Audio/video rooms.
- Screen sharing.
- Participant state.
- Room/participant/track webhooks.
- Backend participant management, including list, remove, and mute published tracks.
- Realtime data transport for text, files/bytes, RPC, and custom events.

Build in Tutorvio:

- Meeting UI.
- Chat UI and persistence.
- Downloadable chat transcript.
- Whiteboard UI or integration.
- File sharing UX and durable file storage.
- Hand raise.
- Participant list UI.
- Teacher controls UI.
- Attendance records.
- Lesson notes.
- Custom classroom events.

Separate service:

- Whiteboard if not implemented directly in Tutorvio.
- Object storage for durable files/recordings if not relying only on provider/egress storage.

Risks:

- Higher initial engineering cost than Daily/Whereby.
- Tutorvio owns browser/device edge cases, accessibility, class moderation UX, and more QA.
- Self-hosting LiveKit adds SFU, TURN, egress, scaling, and monitoring work. LiveKit Cloud reduces operations but not custom UI work.

## Tutorvio-Owned Features

The following should be implemented inside Tutorvio regardless of provider:

- Lesson notes.
- Attendance records and attendance status.
- Durable lesson materials.
- Provider-independent lesson room metadata.
- Join access logs and audit logs.
- Persistent chat history if Tutorvio needs class records, moderation, exports, or compliance review.
- Downloadable chat transcript if chat must be a Tutorvio artifact.
- Teacher-facing lesson controls that affect Tutorvio data, such as mark attendance, submit notes, flag issue, assign homework, or attach resources.
- Custom UI events, such as "student opened lesson notes", "teacher published recap", "whiteboard saved", or "material shared".

## Features Requiring A Separate Provider Or Service

Whiteboard should be planned as a separate module unless Tutorvio chooses to build it directly.

Options:

- Tutorvio-owned whiteboard using a collaborative canvas library such as tldraw, Excalidraw, or Fabric.js plus WebSockets.
- Third-party whiteboard such as Miro, if product accepts third-party board ownership and user/account implications.
- Provider-native whiteboards only for non-MVP convenience, because data ownership and API control are usually weak.

Durable file sharing should also remain Tutorvio-owned. Provider chat file sharing, where available, should be treated as temporary in-meeting exchange, not curriculum material storage.

## Recommended Data Flow

1. User opens a Tutorvio lesson and clicks Join.
2. Frontend calls an authenticated Tutorvio join endpoint using Sanctum.
3. Laravel validates lesson status, role, assigned teacher/student, join window, and access policy.
4. Laravel creates or fetches the provider room and returns short-lived room-scoped join material.
5. Provider handles video/audio, screen sharing, participant presence, and provider-native meeting controls.
6. Tutorvio lesson module handles lesson notes, materials, issue reports, homework links, and any persistent class data.
7. Provider join/leave events update a Tutorvio attendance event table or session participant table.
8. Tutorvio derives attendance status from provider events plus teacher/admin review.
9. Chat is either:
   - provider-native only for MVP with no durable Tutorvio record; or
   - Tutorvio-owned chat persisted through Tutorvio APIs/WebSockets for transcript/export.
10. Whiteboard is either:
   - a separate embedded/integrated provider; or
   - a Tutorvio-owned collaborative board linked to the lesson.
11. Custom classroom UI events are emitted by Tutorvio frontend/backend and stored in Tutorvio, not inferred from provider UI unless supported by provider events.

## MVP Recommendation

Use **Daily Prebuilt** for the first embedded Tutorvio Meet MVP.

MVP native provider features:

- Video/audio.
- Screen sharing.
- In-meeting chat, with a clear product caveat that it is not yet durable Tutorvio chat.
- Hand raise.
- Participant list.
- Teacher/owner meeting controls.
- Join/leave events for attendance inputs.

MVP Tutorvio-owned features:

- Lesson notes.
- Attendance record derived from provider join/leave plus teacher confirmation.
- Lesson materials using existing Tutorvio learning resources/materials patterns.
- Join/access audit logging.

MVP defer or separate:

- Durable chat transcript export.
- Persistent class chat history.
- Whiteboard, unless a simple separate Tutorvio-owned board is specifically prioritized.
- Advanced custom UI events beyond join/leave, notes saved, attendance updated, and material opened.

This keeps the first release small while preserving the correct ownership boundaries: provider for real-time media, Tutorvio for class records.

## Sources

- Daily Prebuilt feature list: <https://docs.daily.co/guides/products/prebuilt>
- Daily Prebuilt customization and feature flags: <https://docs.daily.co/guides/products/prebuilt/customizing-daily-prebuilt>
- Daily webhooks: <https://docs.daily.co/reference/rest-api/webhooks>
- Daily call client events: <https://docs.daily.co/reference/daily-js/daily-call-client>
- Whereby Web Component events and commands: <https://docs.whereby.com/reference/using-the-whereby-embed-element>
- Whereby Embedded feature comparison: <https://docs.whereby.com/further-resources/faq-and-troubleshooting/whereby-embedded-feature-comparison>
- Whereby file sharing: <https://docs.whereby.com/whereby-101/customizing-rooms/file-sharing>
- Google Meet REST API overview: <https://developers.google.com/meet/api/guides/overview>
- Google Meet artifacts: <https://developers.google.com/workspace/meet/api/guides/artifacts>
- Google Meet events: <https://developers.google.com/workspace/events/guides/events-meet>
- Google Meet event response guide: <https://developers.google.com/workspace/meet/api/guides/events-overview>
- LiveKit data features: <https://docs.livekit.io/home/client/data/>
- LiveKit participant management: <https://docs.livekit.io/home/server/managing-participants/>
- LiveKit room service API: <https://docs.livekit.io/reference/other/roomservice-api/>
- LiveKit webhooks and events: <https://docs.livekit.io/home/client/events>
