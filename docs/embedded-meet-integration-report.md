# Embedded Tutorvio Meet/Classroom Integration Report

Date: 2026-05-29

## Executive Summary

Tutorvio has two viable integration tracks:

1. **Fast embedded MVP with provider UI:** use a prebuilt embedded meeting UI from **Daily Prebuilt** or **Whereby Embedded**. This keeps implementation scope low while supporting in-page join, room creation, tokens, chat, screen share, and recording.
2. **Custom Tutorvio classroom UI:** use a programmable WebRTC SDK such as **Daily custom call object**, **LiveKit**, **Vonage Video API**, **Twilio Video**, or **Zoom Video SDK**. This enables a fully branded classroom with Tutorvio-owned controls, but Tutorvio must build the meeting shell, participant grid, device handling, chat, moderation UX, recording controls, and whiteboard integration.

Recommended MVP: **Daily Prebuilt embedded in Tutorvio, backed by Laravel room/token endpoints**, with Tutorvio-owned lesson metadata, access checks, and a separate whiteboard component. Daily has the best balance of iframe/prebuilt speed, custom SDK upgrade path, recording support, meeting tokens, domain model, browser coverage, and lower platform lock-in than Google Meet or Microsoft Teams.

## Evaluation Criteria

- Can the meeting UI be embedded in Tutorvio without opening a new tab?
- Does the provider permit iframe or web-component embedding?
- Can Tutorvio control the meeting UI, or only host provider UI?
- Is there a frontend/backend SDK?
- Can the SDK support camera, microphone, screen share, chat, recording, whiteboard, participant state, and events?
- What backend APIs, credentials, and tokens are required?
- What authentication/session behavior should Tutorvio expect?
- What are the domain, CSP, browser, and product risks?

## Provider Comparison Matrix

| Provider | Iframe/prebuilt feasibility | SDK/custom UI feasibility | Meeting creation | Recording | Chat | Whiteboard | Domain/subdomain support | Complexity | MVP fit |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Zoom Meeting SDK | Medium | Low for custom meeting UI; high with Zoom Video SDK | Yes via REST | Yes | Yes | Zoom Whiteboard is separate/not simple classroom primitive | SDK app + host domains; iframe needs permission headers | Medium/High | Good only if Zoom is required |
| Google Meet | Low | Low for custom UI; Meet Media API is preview/limited | Yes via Meet REST API | Yes, Workspace-dependent | Native Meet chat, limited external control | Native Meet feature, limited external control | Google-owned Meet URL; add-ons run inside Meet | High | Poor for embedded Tutorvio classroom |
| Microsoft Teams | Low for raw iframe | Medium via Azure Communication Services Teams interop | Yes via Graph | Yes, policy/license dependent | Teams chat via meeting; Graph access limited | Meeting setting supports whiteboard | Microsoft tenant/app policies | High | Poor unless Teams interop is strategic |
| Jitsi | High | Medium via lib-jitsi-meet/self-host | Room name or self-host APIs | Yes with Jibri/local options | Yes | No native full classroom whiteboard; integrate separately | Self-host domain or meet.jit.si | Medium | Good open-source option |
| Whereby | High | Medium/High, React Browser SDK | Yes via REST | Yes | Yes | Not a core SDK primitive; integrate external whiteboard | Whereby subdomain, allowed domains, localhost | Low/Medium | Strong MVP option |
| Daily.co | High | High | Yes via REST | Yes | Yes | Not core; integrate external whiteboard | Daily domain, room/token/domain config | Low/Medium | Best MVP option |
| Twilio Video | None/prebuilt no | High | Yes via REST | Yes for group rooms | Via Conversations or DataTrack, not built-in video UI | No | No iframe domain issue; Tutorvio app owns UI | High | Good custom option, verify product strategy |
| Vonage/OpenTok | None/prebuilt no | High | Yes via server SDK/REST | Yes | Via signaling, not stored | No | No iframe domain issue; Tutorvio app owns UI | High | Good custom option |
| LiveKit | None/prebuilt no official iframe UI | High | Yes via server SDK/API | Yes via Egress | Via data packets/components | No | LiveKit Cloud project or self-host domain | Medium/High | Best custom/open architecture option |

## Provider Findings

### Zoom

**Iframe feasibility:** Zoom does not primarily position raw `zoom.us` meeting links as iframe embeds. The supported web embedding path is the **Zoom Meeting SDK for Web**, which renders Zoom meeting UI inside Tutorvio. Zoom also documents iframe best practices when the Meeting SDK route itself is placed inside an iframe: both parent and iframe must be HTTPS, the iframe must allow `camera`, `microphone`, and `display-capture`, and advanced features may require `SharedArrayBuffer` and cross-origin isolation. Source: [Zoom Meeting SDK iframe best practices](https://developers.zoom.us/blog/meeting-sdk-iframe/), [Zoom Meeting SDK Web docs](https://marketplacefront.zoom.us/sdk/meeting/web/index.html).

**SDK feasibility:** Zoom has two materially different SDK paths:

- **Meeting SDK:** embeds the Zoom meeting/webinar experience. Tutorvio can host Zoom UI but cannot fully redesign the classroom. Component View supports start/join, audio/video, screen share, in-meeting chat, cloud recording controls, participants, and events, with some feature gaps versus Client View. Source: [Zoom Meeting SDK supported features](https://developers.zoom.us/docs/meeting-sdk/web/component-view/supported/).
- **Video SDK:** provides programmable audio, video, screen sharing, chat, REST APIs, webhooks, cloud recording, live transcription/translation, and UI Toolkit options. This is the path for a custom Tutorvio classroom. Source: [Zoom Video SDK overview](https://developers.zoom.us/docs/video-sdk/), [Zoom Video SDK UI Toolkit](https://developers.zoom.us/docs/video-sdk/web/ui-toolkit/).

**Backend/API needs:** Meeting creation and recording retrieval use Zoom REST APIs with OAuth or Server-to-Server OAuth. The frontend Meeting SDK also needs a server-generated SDK signature. Source: [Zoom API index](https://developers.zoom.us/docs/api/index/), [Zoom Meeting API](https://developers.zoom.us/docs/api/rest/meeting/).

**Authentication/session behavior:** Users can join from Tutorvio through the SDK if Tutorvio creates meetings and signs SDK joins. Host/recording privileges depend on Zoom account, meeting settings, and role. Meeting SDK credentials are sensitive and must stay server-side.

**Limits/risks:** Zoom is excellent for organizations already using Zoom, but Tutorvio would be constrained by Zoom UI and meeting semantics unless using Zoom Video SDK. Whiteboard/classroom tools may require separate Zoom features or Tutorvio-owned tools. Component View is desktop-focused; mobile behavior needs separate validation.

### Google Meet

**Iframe feasibility:** Google Meet is not a normal embeddable third-party meeting widget for Tutorvio pages. Google provides Meet links and APIs, but the primary user experience remains Google Meet. Google Meet add-ons invert the embedding relationship: Tutorvio content can run inside Meet side panel/main stage if distributed as a Meet add-on, not the Meet call embedded inside Tutorvio. Source: [Meet add-ons quickstart](https://developers.google.com/workspace/meet/add-ons/guides/quickstart).

**SDK feasibility:** Google Meet REST API can create/manage meeting spaces and fetch artifacts such as recordings/transcripts. Google also has Meet Media API in Developer Preview for real-time media access, with enrollment constraints. This is not a general custom classroom SDK comparable to Daily/LiveKit/Twilio. Source: [Google Meet REST API overview](https://developers.google.com/meet/api/guides/overview), [Meet spaces reference](https://developers.google.com/workspace/meet/api/reference/rest/v2/spaces), [Meet Media API get started](https://developers.google.com/workspace/meet/media-api/guides/get-started).

**Backend/API needs:** OAuth with Google Workspace scopes; create `spaces`; configure artifacts such as auto recording/transcription when permitted; fetch conference records/artifacts after meetings. Source: [Configure meeting spaces and members](https://developers.google.com/workspace/meet/api/guides/meeting-spaces-configuration).

**Authentication/session behavior:** Strongly tied to Google identity, Workspace licenses, organizer privileges, and Meet policies. Users generally join Google Meet, not a Tutorvio-native room. Anonymous/external behavior depends on Workspace settings.

**Limits/risks:** Poor fit for an embedded Tutorvio classroom. Good fit only if the requirement is to schedule Google Meet sessions and deep-link users. Real-time media/custom UI is not production-ready for general use because the Media API is in Developer Preview.

### Microsoft Teams

**Iframe feasibility:** Raw Teams meetings are not positioned as iframe widgets for third-party sites. Microsoft supports Teams meeting apps inside Teams and Azure Communication Services (ACS) interoperability for custom apps joining Teams meetings. Source: [Teams meeting app extensibility](https://learn.microsoft.com/en-us/microsoftteams/platform/apps-in-teams-meetings/meeting-app-extensibility), [ACS Teams interoperability](https://learn.microsoft.com/en-us/azure/communication-services/concepts/teams-interop).

**SDK feasibility:** For a Tutorvio web classroom that joins Teams meetings, ACS Calling SDK/UI Library can provide custom app experiences for external users or Teams users. This is not the same as controlling the Teams client UI. Microsoft Graph can create online meetings and expose meeting settings such as recording, chat restrictions, and whiteboard enablement. Source: [Microsoft Graph onlineMeeting resource](https://learn.microsoft.com/en-us/graph/api/resources/onlinemeeting?view=graph-rest-1.0).

**Backend/API needs:** Microsoft Entra app registration, Graph permissions for online meetings, ACS resource/identity/token exchange if using custom calling UI, and tenant policy configuration.

**Authentication/session behavior:** External users can join Teams meetings through ACS without Teams licenses in supported scenarios. Teams users require Microsoft Entra authentication and Teams policies. Organizer/license settings control recording/transcription/whiteboard. Source: [ACS Teams interoperability](https://learn.microsoft.com/en-us/azure/communication-services/concepts/teams-interop).

**Limits/risks:** Complex enterprise stack, tenant policy dependencies, licensing uncertainty, and Microsoft ecosystem coupling. Better for B2C access to existing Teams meetings than a Tutorvio-owned classroom.

### Jitsi

**Iframe feasibility:** Strong. Jitsi Meet has an official IFrame API designed to embed Jitsi meetings into a web app, using `external_api.js`. Tutorvio can keep users inside the Tutorvio page and receive events/issue commands. Source: [Jitsi IFrame API](https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe/).

**SDK feasibility:** The IFrame API offers commands/events for muting, display names, chat messages, participant events, and recording commands. For deeper custom UI, `lib-jitsi-meet` exists but raises implementation complexity because Tutorvio owns more media/session behavior. Source: [Jitsi IFrame commands](https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe-commands).

**Backend/API needs:** Minimal if using public `meet.jit.si` room names, but production Tutorvio should self-host or use a managed Jitsi provider for authentication, branding, recording, and reliability. Secure rooms commonly require JWT or secure-domain configuration.

**Authentication/session behavior:** Public rooms are easy but weakly controlled. JWT/self-hosted auth can bind Tutorvio user identity and moderator role. Recording commonly requires Jibri or provider support.

**Limits/risks:** Great embed story, but production operations are non-trivial if self-hosting: scaling SFU/JVB, TURN, recording workers, monitoring, mobile/browser testing, and abuse control. Whiteboard should be Tutorvio-owned or integrated separately.

### Whereby

**Iframe feasibility:** Strong. Whereby Embedded supports iframe-style low-code embedding and recommends its Web Component for more control. The Web Component embeds prebuilt Whereby UI and exposes commands/events such as camera, microphone, screen share, chat toggle, recording start/stop, participant join/leave, and connection status. Source: [Whereby embed element reference](https://docs.whereby.com/reference/using-the-whereby-embed-element), [Embed with low code](https://docs.whereby.com/whereby-for-web-browser/web-component-and-pre-built-ui/embed-with-low-code).

**SDK feasibility:** Whereby Browser SDK with React hooks enables a custom UI, decoupled from the standard Whereby UI. This is less natural in a Vue app than Daily/LiveKit but still possible via wrapper components or isolated React mount. Source: [Whereby Browser SDK quickstart](https://docs.whereby.com/whereby-for-web-browser/react-based-browser-sdk/quick-start).

**Backend/API needs:** REST API key server-side; create rooms with `endDate`, room mode, room name pattern/prefix; store `roomUrl` and `hostRoomUrl`; issue host URL only to authorized tutors. Source: [Whereby REST room creation](https://docs.whereby.com/whereby-product-features/using-the-rest-api), [Whereby REST API reference](https://whereby.dev/http-api/).

**Authentication/session behavior:** Whereby uses room URLs/keys and host URLs. Tutorvio must protect host URLs and control room creation. Users can join inside Tutorvio if domain is allowed and browser permissions are granted.

**Domain/subdomain behavior:** Whereby uses an account subdomain and supports allowed domains with wildcard subdomains and localhost ports. Source: [Whereby allowed domains](https://docs.whereby.com/whereby-101/faq-and-troubleshooting/allowed-domains-and-localhost).

**Limits/risks:** Excellent low-code UX but custom UI path is React-oriented. Whiteboard is not a core programmable primitive; use an external whiteboard. Vendor UI and room behavior remain visible in prebuilt mode.

### Daily.co

**Iframe feasibility:** Strong. Daily Prebuilt is an embeddable video widget; Daily's frontend library uses an iframe internally for embedded calls. Daily documents browser and mobile behavior, including that iOS often works better as a new tab because of limited screen space. Source: [Daily browser support](https://docs.daily.co/guides/architecture-and-monitoring/browsers), [Daily docs overview](https://docs.daily.co/).

**SDK feasibility:** Strong. Daily supports Prebuilt for fast launch and custom video/audio layouts with client SDKs/call object. Features include camera/microphone, screen sharing, participants/events, chat options, recording, transcription, and programmatic room/token control. Source: [Daily Video SDK](https://www.daily.co/products/video-sdk/), [Daily get started](https://docs.daily.co/get-started).

**Backend/API needs:** Daily API key server-side; create rooms via `POST /rooms`; issue meeting tokens via `POST /meeting-tokens`; configure room privacy, permissions, recording, prejoin UI, expiration/ejection, user names, and owner privileges. Source: [Daily create room](https://docs.daily.co/reference/rest-api/rooms/create-room), [Daily create meeting token](https://docs.daily.co/reference/rest-api/meeting-tokens/create-meeting-token).

**Authentication/session behavior:** Tutorvio authenticates users, then backend issues short-lived room-scoped Daily meeting tokens. Tutors can receive owner tokens; students receive restricted tokens. Tokens can expire/eject users and control recording button access.

**Domain/subdomain behavior:** Daily accounts have a Daily domain that is the top-level API object; rooms live under that domain. Domain-level configuration can control prebuilt behavior and recording accessibility. Source: [Daily domain](https://help.daily.co/en/articles/4202492-your-daily-domain), [Daily domain config](https://docs.daily.co/reference/rest-api/your-domain/set-domain-config).

**Limits/risks:** Whiteboard remains Tutorvio-owned or third-party. Prebuilt UI limits full brand control, but Daily has a clean path from Prebuilt to custom call object without changing provider.

### Twilio Video

**Iframe feasibility:** No native provider meeting UI to iframe. Twilio Video is a programmable media SDK, so Tutorvio builds the UI.

**SDK feasibility:** Strong custom UI. Twilio Video JS SDK supports room connection, local/remote audio/video tracks, screen share, participant events, network tooling, DataTrack for app messages, and REST APIs for rooms/participants/recordings/compositions. Chat is typically implemented with Twilio Conversations or DataTrack, not as a built-in meeting UI. Source: [Twilio Video docs](https://www.twilio.com/docs/video), [Twilio JavaScript getting started](https://www.twilio.com/docs/video/javascript-v2-getting-started).

**Backend/API needs:** Server creates rooms or allows ad hoc rooms, generates short-lived Access Tokens with Video grants, and uses REST API for room status, recording, and compositions. Source: [Twilio access tokens](https://www.twilio.com/docs/video/tutorials/user-identity-access-tokens), [Twilio Video REST API](https://www.twilio.com/docs/video/api), [Twilio Rooms resource](https://www.twilio.com/docs/video/api/rooms-resource).

**Authentication/session behavior:** Tutorvio authenticates users and issues Twilio JWT access tokens scoped to a room. Tokens require Twilio Account SID, API Key SID, and API Key Secret server-side.

**Recording:** Group room recording is supported; peer-to-peer rooms cannot be recorded via Twilio's REST API. Source: [Twilio recording help](https://help.twilio.com/articles/360035005493).

**Limits/risks:** Higher engineering effort because Tutorvio owns the classroom UI. Product strategy should be verified commercially because Twilio previously announced and then removed an end-of-life notice for Programmable Video. Source: [Twilio HIPAA eligible services changelog](https://www.twilio.com/content/dam/twilio-com/global/en/other/hipaa/pdf/HIPAA-Eligible-Services.pdf).

### Vonage/OpenTok

**Iframe feasibility:** No primary prebuilt meeting iframe comparable to Daily/Whereby. Tutorvio builds UI with the Web SDK.

**SDK feasibility:** Strong custom UI. Vonage Video API supports real-time video, screen sharing, messaging/signaling, recording, broadcasting, encryption, live captions, server SDKs, REST APIs, sessions, tokens, and callbacks. Source: [Vonage Video overview](https://developer.vonage.com/en/video/overview), [Vonage technical details](https://developer.vonage.com/en/video/technical-details).

**Backend/API needs:** App server creates sessions, generates tokens/roles, manages archives/recordings, handles callbacks/webhooks, and controls moderation/storage. Source: [Vonage technical details](https://developer.vonage.com/en/video/technical-details).

**Authentication/session behavior:** Tutorvio authenticates users and issues Vonage session tokens from the backend. Role controls publisher/subscriber/moderator capabilities.

**Recording/chat/whiteboard:** Recording is via archives. Chat can be implemented via Vonage signaling, but messages are not stored by Vonage Video. Whiteboard is separate. Source: [Vonage best practices/chat note](https://developer.vonage.com/en/video/video-best-practices?source=video).

**Browser/domain limits:** Screen share requires HTTPS and browser support; iframe deployments need `allow="camera; microphone; display-capture"` if Tutorvio isolates the app in an iframe. Source: [Vonage screen sharing](https://developer.vonage.com/en/video/guides/screen-sharing/js-only), [Vonage iframe screen share support note](https://api.support.vonage.com/hc/en-us/articles/6646175567772-Issue-with-screensharing-with-embedded-iFrames).

### LiveKit

**Iframe feasibility:** No official provider meeting UI intended for iframe embedding. Tutorvio builds or adopts open-source components/templates.

**SDK feasibility:** Strong custom UI and architecture. LiveKit has SDKs across web/mobile/server, room/participant abstractions, audio/video, screen share, data packets for chat/app events, webhooks, and server APIs. Source: [LiveKit connecting](https://docs.livekit.io/intro/basics/connect/), [LiveKit screen sharing](https://docs.livekit.io/transport/media/screenshare/), [LiveKit data packets](https://docs.livekit.io/transport/data/packets/).

**Backend/API needs:** LiveKit Cloud or self-hosted LiveKit; Laravel creates rooms if desired and generates JWT access tokens with room grants. Recording/livestreaming uses Egress service/API and requires `roomRecord` permission. Source: [LiveKit Egress overview](https://docs.livekit.io/server/egress/), [LiveKit Egress API](https://docs.livekit.io/home/egress/api).

**Authentication/session behavior:** Tutorvio owns identity and permissions. Backend issues participant tokens scoped to room, role, can-publish/can-subscribe/can-record behavior. This maps well to tutor/student roles.

**Recording/chat/whiteboard:** Recording via Egress; chat via data packets or LiveKit components; whiteboard is external. LiveKit is a strong fit if Tutorvio wants a platform-owned classroom with future AI/analytics/control features.

**Limits/risks:** More initial build than Daily/Whereby prebuilt. Self-hosting adds SFU/TURN/egress operational burden; LiveKit Cloud reduces that but keeps custom UI work.

## Cross-Cutting Browser and Security Notes

- Embedded camera/microphone/screen share requires HTTPS in production.
- If Tutorvio places a meeting route inside an iframe, set iframe permissions such as `allow="camera; microphone; display-capture; fullscreen"` as applicable.
- Screen sharing has browser and OS limitations. Desktop browsers are strongest; iOS mobile screen share is generally limited or unsupported depending on provider.
- Cross-origin isolation may be needed for higher-performance SDK features using `SharedArrayBuffer`, especially Zoom Meeting SDK advanced web features.
- Raw provider sites can block framing via `X-Frame-Options` or `Content-Security-Policy: frame-ancestors`; use official embed SDKs/components rather than arbitrary meeting URLs. Source: [MDN X-Frame-Options](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Frame-Options).
- Tutorvio should keep provider API keys/secrets only in Laravel environment variables and issue short-lived, room-scoped participant tokens to the frontend.

## Authentication and Session Model for Tutorvio

Recommended general model:

1. Tutorvio schedule/class session is the source of truth.
2. Laravel validates the authenticated Tutorvio user and their role in the class.
3. Laravel creates or reuses a provider room for the class occurrence.
4. Laravel issues a provider token scoped to that room and user role.
5. Frontend joins inside the classroom page.
6. Tutorvio stores provider room ID, room URL, recording artifact IDs, provider meeting IDs, and session attendance metadata.
7. Webhooks update session status, recording availability, and participant events where supported.

Role mapping:

- Tutor: owner/moderator token, can start/end session, record, mute/remove participants, screen share.
- Student: participant token, can join during allowed time window, camera/mic/screen share permissions configurable by class policy.
- Admin: optional observer/moderator token, audit access to artifacts.

## Meeting Creation Support

- **Best simple REST creation:** Daily, Whereby, Zoom, Twilio, Vonage, Google Meet, Microsoft Graph.
- **Best self-owned programmable rooms:** LiveKit, Twilio, Vonage, Daily custom.
- **Weak creation story for embedded classroom:** Google Meet and Teams create meetings well but keep users in Google/Microsoft meeting models.
- **Jitsi:** room names are easy; production-grade creation/auth is mostly deployment/configuration rather than a single SaaS REST room lifecycle unless using a managed Jitsi provider.

## Recording Support

- **Strong built-in/prebuilt:** Daily, Whereby, Zoom, Teams, Google Meet.
- **Strong programmable:** LiveKit Egress, Twilio group room recordings/compositions, Vonage archives, Zoom Video SDK cloud recording.
- **Operationally heavier:** self-hosted Jitsi recording via Jibri; self-hosted LiveKit Egress.
- **Policy/license dependent:** Google Meet, Microsoft Teams, Zoom.

## Chat Support

- **Native in provider UI:** Zoom Meeting SDK, Google Meet, Teams, Jitsi, Whereby, Daily Prebuilt.
- **Programmable/custom:** Daily, LiveKit data packets, Twilio Conversations/DataTrack, Vonage signaling, Zoom Video SDK chat.
- **Storage caveat:** Some media SDKs provide transport/signaling but not durable chat history; Tutorvio may need to persist chat messages itself for classes and compliance.

## Whiteboard Support

No reviewed provider is ideal as the sole Tutorvio classroom whiteboard primitive.

- Google Meet/Teams/Zoom may have native whiteboard features, but external control and data ownership are limited.
- Daily/Whereby/Jitsi/Twilio/Vonage/LiveKit should be paired with a Tutorvio-owned whiteboard such as Excalidraw/Tldraw/Fabric.js plus provider data channels or Tutorvio WebSockets.
- MVP should treat whiteboard as a separate classroom panel, not a dependency of the video provider.

## Estimated Complexity

Low:

- Daily Prebuilt iframe/embed + Laravel room/token endpoints.
- Whereby Web Component + Laravel room creation.

Medium:

- Jitsi iframe with self-hosted auth/branding, no advanced custom UI.
- Zoom Meeting SDK component view with signature endpoint and meeting creation.
- Daily custom UI for essential controls.

High:

- LiveKit, Twilio, Vonage, Zoom Video SDK fully custom classroom.
- Microsoft Teams ACS interop.
- Google Meet add-on or Media API paths.

Very high:

- Self-hosted Jitsi or LiveKit with recording, TURN, scaling, monitoring, mobile QA, and compliance from day one.

## Risks and Limitations

- **Provider UI lock-in:** Zoom Meeting SDK, Daily Prebuilt, Whereby Web Component, Jitsi iframe expose provider-specific UI/UX.
- **Custom UI cost:** Programmable SDKs require substantial frontend state management, device testing, network edge-case handling, and accessibility work.
- **Recording consent/compliance:** Tutorvio must show clear in-product recording indicators and store consent/audit metadata.
- **Mobile constraints:** Embedded calls on mobile may be cramped; Daily explicitly notes iOS may be better in a new tab for some flows.
- **Screen share constraints:** Desktop support is strongest; mobile publishing is limited across providers.
- **Enterprise identity complexity:** Google Meet and Teams are highly dependent on Workspace/Microsoft tenant settings, licenses, and admin policies.
- **Whiteboard gap:** Most SDKs do not provide a Tutorvio-owned whiteboard; plan for a separate implementation.
- **CSP/permissions:** Iframe-based integrations need correct `allow` attributes and Tutorvio CSP rules for provider scripts, frames, media, workers, and websockets.
- **Data ownership:** Native provider chat/recording/attendance may not map cleanly to Tutorvio records; webhook/API coverage must be validated before compliance commitments.

## Recommended MVP

Use **Daily Prebuilt embedded in Tutorvio**.

Rationale:

- Embeds directly in the Tutorvio classroom page with minimal frontend code.
- Supports room creation and meeting tokens through REST APIs that Laravel can own.
- Allows role-scoped tutor/student tokens, room privacy, expiration/ejection, owner privileges, and recording permissions.
- Has a clean upgrade path from prebuilt UI to custom Daily call object if Tutorvio later needs a fully branded classroom.
- Avoids Google/Microsoft tenant complexity and Zoom meeting UI constraints.
- Reduces operational burden versus self-hosted Jitsi or LiveKit.

MVP architecture:

- Laravel creates Daily rooms for scheduled class sessions.
- Laravel issues short-lived Daily meeting tokens after Tutorvio authorization checks.
- Vue classroom page embeds Daily Prebuilt and passes the room URL/token.
- Tutorvio stores class session, provider room name/URL, user join metadata, recording IDs, and webhook events.
- Tutorvio adds a separate whiteboard panel owned by Tutorvio rather than relying on provider whiteboard.
- Recording starts only for authorized tutor/admin action or clearly configured class policy.

Second-phase path:

- If Tutorvio needs deeper classroom control but wants managed infrastructure, move from Daily Prebuilt to Daily custom call object.
- If Tutorvio needs maximum ownership, open architecture, self-host option, and future AI/media workflows, evaluate **LiveKit Cloud** as the custom classroom foundation.

## Source Index

- Zoom Meeting SDK iframe best practices: https://developers.zoom.us/blog/meeting-sdk-iframe/
- Zoom Meeting SDK Web: https://marketplacefront.zoom.us/sdk/meeting/web/index.html
- Zoom Meeting SDK supported features: https://developers.zoom.us/docs/meeting-sdk/web/component-view/supported/
- Zoom Video SDK: https://developers.zoom.us/docs/video-sdk/
- Zoom API: https://developers.zoom.us/docs/api/index/
- Google Meet REST API: https://developers.google.com/meet/api/guides/overview
- Google Meet spaces: https://developers.google.com/workspace/meet/api/reference/rest/v2/spaces
- Google Meet add-ons: https://developers.google.com/workspace/meet/add-ons/guides/quickstart
- Google Meet Media API: https://developers.google.com/workspace/meet/media-api/guides/get-started
- Microsoft Teams meeting apps: https://learn.microsoft.com/en-us/microsoftteams/platform/apps-in-teams-meetings/meeting-app-extensibility
- Microsoft Graph onlineMeeting: https://learn.microsoft.com/en-us/graph/api/resources/onlinemeeting
- Azure Communication Services Teams interop: https://learn.microsoft.com/en-us/azure/communication-services/concepts/teams-interop
- Jitsi IFrame API: https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe/
- Jitsi IFrame commands: https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe-commands
- Whereby Embedded docs: https://docs.whereby.com/
- Whereby embed element: https://docs.whereby.com/reference/using-the-whereby-embed-element
- Whereby allowed domains: https://docs.whereby.com/whereby-101/faq-and-troubleshooting/allowed-domains-and-localhost
- Daily docs: https://docs.daily.co/
- Daily browser support: https://docs.daily.co/guides/architecture-and-monitoring/browsers
- Daily rooms API: https://docs.daily.co/reference/rest-api/rooms/create-room
- Daily meeting tokens API: https://docs.daily.co/reference/rest-api/meeting-tokens/create-meeting-token
- Twilio Video docs: https://www.twilio.com/docs/video
- Twilio Video REST API: https://www.twilio.com/docs/video/api
- Twilio access tokens: https://www.twilio.com/docs/video/tutorials/user-identity-access-tokens
- Vonage Video overview: https://developer.vonage.com/en/video/overview
- Vonage technical details: https://developer.vonage.com/en/video/technical-details
- LiveKit connect: https://docs.livekit.io/intro/basics/connect/
- LiveKit screen sharing: https://docs.livekit.io/transport/media/screenshare/
- LiveKit Egress: https://docs.livekit.io/server/egress/
- MDN X-Frame-Options: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Frame-Options
