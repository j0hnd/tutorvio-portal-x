# Chat Redis State

This document describes Redis usage for the `Conversation*` chat system.
Redis stores short-lived realtime and cache state only. MariaDB remains the
source of truth for chat history, participants, read state, attachments,
reminders, pins, escalations, and message templates.

## Configuration

Chat realtime state is configured in `config/chat.php` under `chat.realtime`.

Default environment values:

```env
CHAT_REALTIME_ENABLED=true
CHAT_REALTIME_CACHE_STORE=redis
CHAT_REALTIME_KEY_NAMESPACE=tvio:chat
CHAT_TYPING_TTL_SECONDS=10
CHAT_PRESENCE_TTL_SECONDS=60
CHAT_ACTIVE_CONVERSATION_TTL_SECONDS=120
CHAT_UNREAD_COUNT_TTL_SECONDS=30
CHAT_DUPLICATE_REMINDER_LOCK_TTL_SECONDS=300
CHAT_DELIVERY_STATUS_TTL_SECONDS=86400
CHAT_RATE_LIMIT_DECAY_SECONDS=60
```

`CHAT_REALTIME_CACHE_STORE` defaults to `CACHE_STORE` when unset. Use a Redis
store in environments that need realtime fan-out or cross-process cache state.
Unit tests may use `array` to keep Redis-independent coverage.

## Key Naming Convention

`App\Services\ChatRealtimeStateService` builds all chat realtime keys as:

```text
{namespace}:{environment}:{state_type}:{identifier...}
```

Defaults:

- `namespace`: `tvio:chat`, configurable with `CHAT_REALTIME_KEY_NAMESPACE`.
- `environment`: `CHAT_REALTIME_ENVIRONMENT`, falling back to `APP_ENV`.
- `state_type`: one of the chat state names listed below.
- Identifiers: public IDs or internal IDs supplied by the calling code.

Every segment after the namespace is sanitized by replacing characters outside
`A-Z`, `a-z`, `0-9`, `_`, `.`, and `-` with `_`. Empty segments become `none`.

Example:

```text
tvio:chat:production:typing:cnv_123:usr_456
```

## Redis Keys And TTLs

| State | Key pattern | Default TTL | Purpose |
| --- | --- | ---: | --- |
| Typing indicator | `tvio:chat:{env}:typing:{conversation_id}:{user_id}` | 10 seconds | Stores a user's temporary typing state for a conversation. `stopTyping` deletes the key early. |
| Presence | `tvio:chat:{env}:presence:{user_id}` | 60 seconds | Stores online or away state plus lightweight metadata and `seen_at`. |
| Active conversation | `tvio:chat:{env}:active_conversation:{user_id}` | 120 seconds | Stores the conversation a user is currently viewing so notification and unread behavior can avoid stale active-window assumptions. |
| Unread count cache | `tvio:chat:{env}:unread_count:{user_id}` | 30 seconds | Caches the computed unread conversation count. Reads fall back to the MariaDB query callback when Redis is disabled or unavailable. |
| Duplicate reminder lock | `tvio:chat:{env}:duplicate_reminder_lock:{conversation_id}:{reminder_key}` | 300 seconds | Atomic `add` lock that suppresses duplicate reminder attempts for the same conversation and reminder key. This is best-effort only; durable dedupe still belongs in MariaDB. |
| Delivery status | `tvio:chat:{env}:delivery_status:{message_id}:{recipient_id}` | 86,400 seconds | Stores transient delivery status hints such as delivered or failed for a message-recipient pair. Permanent message status remains in MariaDB. |
| Chat helper rate limit | `tvio:chat:{env}:rate_limit:{name}:{identifier}` | 60 seconds | Used by `ChatRealtimeStateService::hitRateLimit` for chat-specific helper limits. Max attempts default to `typing=30`, `presence=60`, `message_send=30`, and `reminder=10`. |

HTTP chat routes also use Laravel route throttles such as `api-action`,
`api-upload`, and `api-download`. When `RATE_LIMITER_STORE=redis` or
`CACHE_STORE=redis`, Laravel stores those throttle counters in Redis through its
rate limiter middleware. Those middleware keys are framework-managed and are
separate from the `tvio:chat:{env}:rate_limit:*` helper keys above.

## Fallback Behavior

Chat Redis state is best-effort:

- If `CHAT_REALTIME_ENABLED=false`, cache reads return defaults, cache writes
  return `false`, unread counts run their MariaDB callback, duplicate reminder
  locks are not acquired, and rate limit helper calls do not block requests.
- If Redis or the configured cache store fails, the service logs a warning with
  a `cache_area` value and then falls back where possible.
- Typing, presence, active-conversation, delivery-status, and duplicate-lock
  writes may be skipped when Redis is down.
- Unread count reads must recompute from MariaDB when the cache is unavailable.
- Redis failure must not erase, replace, or become authoritative for persisted
  chat records.

Do not make chat history depend on Redis availability. If a behavior needs to be
auditable, replayable, or visible after Redis eviction, store it in MariaDB.

## MariaDB Source Of Truth

The following data remains permanent in MariaDB:

- `conversations`: conversation type, title, status, student/teacher/course
  references, creator, last-message summary fields, metadata, timestamps, and
  soft deletes.
- `conversation_participants`: membership, participant role snapshots,
  `last_read_at`, `last_read_message_id`, mute/archive state, metadata,
  timestamps, and soft deletes.
- `conversation_messages`: message body, links, attachment metadata, sender,
  status, edit timestamp, metadata, timestamps, and soft deletes.
- `conversation_attachments`: uploaded file and link records, storage location,
  MIME type, size, uploader, metadata, timestamps, and soft deletes.
- `conversation_message_pins`: pinned message records and who pinned them.
- `chat_reminders`: reminder type, source reference, durable `dedupe_key`,
  status, recipients, sent timestamp, and metadata.
- `conversation_escalations`: escalation status, reason, notes, reviewer,
  linked issue report, review/resolution timestamps, metadata, and soft deletes.
- `message_templates`: reusable message template body, category, role
  visibility, status, teacher scope, creator/updater, timestamps, and soft
  deletes.

Redis must not be used as the source of truth for chat history. Message lists,
conversation detail, authorization decisions, audit-sensitive reminder state,
read receipts, escalations, pins, attachments, and templates must be read from
MariaDB or from services that fall back to MariaDB.
