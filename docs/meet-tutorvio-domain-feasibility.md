# meet.tutorvio.com Feasibility

Date: 2026-05-29

## Summary

`meet.tutorvio.com` is realistic for Tutorvio's classroom entry point, but not realistic as a universal replacement for provider-hosted meeting domains.

The most realistic MVP interpretation is:

1. Host a Tutorvio-owned classroom page at `https://meet.tutorvio.com`.
2. Authenticate users through Tutorvio/Laravel.
3. Create or fetch provider rooms server-side.
4. Embed or mount the provider's SDK/prebuilt UI inside the Tutorvio page.
5. Treat provider room URLs, tokens, and recording IDs as backend-managed implementation details.

For the current recommended MVP from `docs/embedded-meet-integration-report.md`, this means `meet.tutorvio.com` can be the branded Tutorvio classroom shell while Daily rooms still live under the Daily account domain, for example `https://tutorvio.daily.co/<room>`.

## Interpretation Matrix

| Interpretation | Realistic? | Notes |
| --- | --- | --- |
| `meet.tutorvio.com` as a fully self-hosted meeting system | Only if Tutorvio self-hosts a stack such as Jitsi or LiveKit | Feasible technically, but not MVP-friendly. Requires SFU/WebRTC operations, TURN, SSL, scaling, monitoring, recording, abuse controls, and browser QA. |
| `meet.tutorvio.com` as a Tutorvio frontend using a provider SDK | Yes | Best long-term interpretation. The domain hosts Tutorvio's classroom UI and uses Daily custom call object, LiveKit, Twilio, Vonage, Zoom Video SDK, or similar behind it. |
| `meet.tutorvio.com` as a redirect/wrapper to provider-hosted meeting URLs | Yes, with limited branding | Simple fallback. The page can authorize the user, log access, then redirect/open the provider URL. Branding mostly disappears once the user is on the provider domain. |
| `meet.tutorvio.com` as a branded iframe page | Yes for iframe-friendly providers only | Works with Daily Prebuilt, Whereby Embedded, and Jitsi iframe. Does not work reliably for arbitrary Google Meet, Teams, or raw Zoom meeting URLs. Use official SDK/embed paths. |

## Provider Custom-Domain Limits

### Daily

Daily is the best fit for the existing MVP recommendation, but `meet.tutorvio.com` should be understood as Tutorvio's app domain, not as the Daily room domain.

- Daily rooms are created under a Daily account domain. Daily describes the Daily domain as the top-level API object and says each Daily account creates one domain with one API key.
- A Daily room URL is therefore normally `https://<daily-domain>.daily.co/<room>`.
- Daily domain configuration controls Prebuilt UI features, language, redirect-on-exit, recording storage, and whether "Powered by Daily" appears. Hiding Daily branding is a paid/pay-as-you-go feature in the Daily API docs.
- Daily Prebuilt can be embedded in a Tutorvio page. Daily's JavaScript library uses an iframe internally for embedded calls.
- The custom SDK/call-object path supports a much more Tutorvio-owned UI, but the media service remains Daily.
- I did not find an official Daily path that turns arbitrary customer DNS such as `meet.tutorvio.com` into the canonical Daily room hostname. Plan on using `meet.tutorvio.com` as the wrapper/app host and Daily's domain as the provider room namespace.

Required settings:

- DNS/SSL for `meet.tutorvio.com`.
- Tutorvio CSP allowing Daily scripts, frames, media, websocket/connect endpoints, and any recording/storage endpoints used.
- Iframe permissions such as `camera`, `microphone`, `display-capture`, and `fullscreen`.
- Laravel-held Daily API key.
- Private rooms and short-lived room-scoped meeting tokens.
- Optional `redirect_on_meeting_exit` pointing back to Tutorvio.

Sources:

- Daily domain: <https://help.daily.co/en/articles/4202492-your-daily-domain>
- Daily domain config: <https://docs.daily.co/reference/rest-api/your-domain/set-domain-config>

### Whereby

Whereby can support `meet.tutorvio.com` as a wrapper or deployed prebuilt app, but the underlying rooms remain tied to the Whereby account/subdomain.

- Whereby room creation returns a `roomUrl` under the Whereby account domain, for example `https://your-account.whereby.com/<room>`.
- Whereby documents a wrapper deployment pattern where `https://video.yoursite.com/<roomName>` loads a Whereby room from the Whereby subdomain.
- Whereby has an Allowed Domains feature. When using the `<whereby-embed>` web component for commands/events, the application origin must be listed. Wildcards such as `https://*.domain.com` are supported.
- Low-code iframe embedding requires iframe `allow` permissions for camera, microphone, fullscreen, display capture, autoplay, and related browser features.
- Branding is limited in prebuilt/iframe mode; deeper control uses Whereby's SDK path.

Required settings:

- DNS/SSL for `meet.tutorvio.com`.
- Add `https://meet.tutorvio.com` or `https://*.tutorvio.com` to Whereby Allowed Domains.
- Tutorvio CSP allowing Whereby frame/script/connect/media endpoints.
- Backend-held Whereby API key.
- Protect host room URLs server-side.

Sources:

- Whereby allowed domains: <https://docs.whereby.com/whereby-101/faq-and-troubleshooting/allowed-domains-and-localhost>
- Whereby low-code iframe embed: <https://docs.whereby.com/whereby-101/readme/in-a-web-page/using-the-whereby-embed-element/with-low-code>
- Whereby wrapper deployment: <https://docs.whereby.com/developer-guides/quickly-deploy-whereby-to-your-domain>

### Jitsi

Jitsi is the clearest path if Tutorvio wants `meet.tutorvio.com` to be the actual meeting host.

- Self-hosted Jitsi can run at `https://meet.tutorvio.com`.
- Jitsi's iframe API explicitly supports loading `external_api.js` from a self-hosted domain.
- The iframe API also works against `meet.jit.si`, but public `meet.jit.si` rooms are not a strong production access-control model for Tutorvio lessons.
- A production Tutorvio deployment needs secure-domain/JWT-style access, TURN, Jitsi Videobridge scaling, recording via Jibri if needed, monitoring, upgrades, and abuse controls.

Required settings:

- DNS pointing `meet.tutorvio.com` to the Jitsi web/reverse-proxy host.
- Valid TLS certificate.
- Jitsi `PUBLIC_URL=https://meet.tutorvio.com`.
- WebRTC media/TURN networking.
- Secure room authentication and role mapping.
- Optional Jibri/recording infrastructure.

Sources:

- Jitsi iframe API: <https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe/>
- Jitsi Docker self-hosting: <https://jitsi.github.io/handbook/docs/devops-guide/devops-guide-docker/>

### LiveKit

LiveKit is realistic for a fully Tutorvio-owned classroom UI at `meet.tutorvio.com`, but not as a low-code embedded provider room.

- LiveKit provides SDKs and room infrastructure; Tutorvio builds the classroom frontend.
- LiveKit Cloud and self-hosted LiveKit use the same SDK/API model, so Tutorvio can start managed and later self-host with less application rewrite.
- `meet.tutorvio.com` can host the Tutorvio app. The LiveKit server endpoint can be LiveKit Cloud or a self-hosted endpoint.
- If fully self-hosted, Tutorvio owns WebRTC infrastructure, TURN, egress/recording, observability, and scaling.

Required settings:

- DNS/SSL for the Tutorvio classroom frontend.
- LiveKit websocket/API endpoint configuration.
- Backend JWT token minting with room grants.
- CSP `connect-src` for LiveKit websocket endpoints.
- Egress/recording storage configuration if recording is required.

Source:

- LiveKit Cloud/self-host comparison: <https://docs.livekit.io/intro/cloud/>

### Zoom

Zoom is not a good interpretation of `meet.tutorvio.com` as a custom meeting URL.

- Zoom Meeting SDK can render the Zoom meeting experience inside Tutorvio's site, but the UI still resembles Zoom.
- The web SDK supports client and component views in Tutorvio's app.
- Zoom meeting creation/join still uses Zoom meeting IDs/meeting semantics and SDK authorization.
- Zoom custom domains are documented for Zoom Events branding, not as a general way to move normal Zoom Meetings onto `meet.tutorvio.com`.
- Zoom Video SDK is a better fit for custom Tutorvio UI than Zoom Meeting SDK, but Tutorvio must build the product experience.

Required settings:

- DNS/SSL for Tutorvio wrapper/app.
- Zoom app/SDK credentials and server-generated signatures/tokens.
- OAuth or server-to-server OAuth for backend APIs where needed.
- Iframe permissions and possibly cross-origin isolation for advanced web features.
- CSP for Zoom SDK assets/connectivity.

Sources:

- Zoom Meeting SDK: <https://developers.zoom.us/docs/meeting-sdk/>
- Zoom Events custom domain: <https://support.zoom.com/hc/en/article?id=zm_kb&sysparm_article=KB0057580>

### Google Meet

Google Meet is not realistic as `meet.tutorvio.com` for an embedded Tutorvio classroom.

- Google Meet API-created spaces expose a `meetingUri` under `https://meet.google.com/<code>`.
- Google controls the primary meeting surface.
- Google documents Meet entry points such as `meet.google.com`, Meet Embed SDK Web, and mobile Meet SDKs, but this does not make `meet.tutorvio.com` a Google Meet room hostname.
- A Tutorvio page can redirect to Google Meet or perhaps create an owned entry point where supported, but the classroom is not fully Tutorvio-owned.

Required settings:

- Google Cloud project and OAuth scopes for Meet APIs.
- Workspace policies/licenses for recording, artifacts, and external access.
- OAuth redirect URIs if users/admins authorize Google access.
- Treat Google Meet URLs as external provider links, not embedded arbitrary iframes.

Source:

- Google Meet spaces resource: <https://developers.google.com/workspace/meet/api/reference/rest/v2/spaces>

### Microsoft Teams

Teams is not realistic as `meet.tutorvio.com` for direct provider-hosted meeting pages.

- Teams meetings remain Microsoft/Teams meeting experiences.
- Teams supports apps/tabs inside Teams, and Azure Communication Services can interoperate with Teams meetings, but that is a Microsoft ecosystem integration rather than a Tutorvio-hosted meeting domain.
- `meet.tutorvio.com` can host a custom ACS-based frontend if Tutorvio chooses that route, with tenant/app/policy complexity.

Required settings:

- Microsoft Entra app registration.
- Graph permissions for online meetings if creating Teams meetings.
- ACS resource and token exchange if building a custom Teams-interop experience.
- Tenant policy/license configuration.
- DNS/SSL/CSP for Tutorvio app host.

Sources:

- Teams meeting apps: <https://learn.microsoft.com/en-us/microsoftteams/platform/apps-in-teams-meetings/teams-apps-in-meetings>
- Azure Communication Services Teams interoperability: <https://learn.microsoft.com/en-us/azure/communication-services/concepts/teams-interop>

## DNS and SSL Requirements

For all realistic uses of `meet.tutorvio.com`:

- Create DNS for `meet.tutorvio.com`, usually a CNAME to the frontend hosting provider or an A/AAAA record to Tutorvio infrastructure.
- Serve the page over HTTPS with a valid certificate.
- Configure Laravel/Sanctum/CORS/session settings if this host talks to the existing API as a first-party SPA domain.
- Add `meet.tutorvio.com` to OAuth redirect URIs for providers that require OAuth.
- Add `meet.tutorvio.com` to provider allowed-domain settings where required, especially Whereby.
- Add CSP rules for `frame-src`, `script-src`, `connect-src`, `media-src`, `img-src`, and `worker-src` according to the chosen provider.
- Use iframe `allow` permissions for camera, microphone, screen sharing/display capture, autoplay when needed, and fullscreen.

## Branding Limits

`meet.tutorvio.com` improves the outer classroom brand: URL, navigation, lesson context, whiteboard, chat side panels, loading states, authorization errors, and post-class flows.

It does not automatically remove provider branding inside prebuilt meeting UIs:

- Daily Prebuilt can hide "Powered by Daily" on eligible paid configuration, but it remains a Daily Prebuilt UI.
- Whereby iframe/web component remains Whereby's prebuilt room UI, though feature toggles and wrapper branding help.
- Zoom Meeting SDK remains Zoom-like on web.
- Google Meet and Teams remain Google/Microsoft experiences.
- Jitsi self-host can be heavily branded, but Tutorvio then owns the infrastructure.
- Fully custom SDK paths such as Daily call object, LiveKit, Twilio, Vonage, or Zoom Video SDK provide the most brand control, with higher engineering cost.

## Recommended MVP Domain Strategy

Use `meet.tutorvio.com` as Tutorvio's classroom frontend domain, not as the provider room hostname.

For MVP:

1. Point `meet.tutorvio.com` to the Tutorvio frontend or a dedicated classroom frontend.
2. Use a route such as `https://meet.tutorvio.com/lessons/{lesson}/join`.
3. Require Tutorvio authentication before any provider room/token is exposed.
4. Use Daily Prebuilt embedded inside that page, backed by Laravel room/token endpoints.
5. Store provider metadata server-side: provider, room ID/name, Daily room URL, room expiry, recording IDs, and configuration version.
6. Keep `app.tutorvio.com` or the existing frontend domain for the main portal, and reserve `meet.tutorvio.com` for the focused live classroom surface.
7. On iOS/mobile or provider/browser edge cases, allow the Tutorvio page to open a provider URL in a new tab after authorization.

This preserves the `meet.tutorvio.com` brand in the user flow without assuming that Daily, Whereby, Zoom, Google Meet, or Teams can serve native provider rooms directly from Tutorvio DNS.
