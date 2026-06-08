<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ChatRealtimeStateService
{
    public const DEFAULT_TYPING_TTL_SECONDS = 10;

    public const DEFAULT_PRESENCE_TTL_SECONDS = 60;

    public const DEFAULT_ACTIVE_CONVERSATION_TTL_SECONDS = 120;

    public const DEFAULT_UNREAD_COUNT_TTL_SECONDS = 30;

    public const DEFAULT_DUPLICATE_REMINDER_LOCK_TTL_SECONDS = 300;

    public const DEFAULT_DELIVERY_STATUS_TTL_SECONDS = 86400;

    public const DEFAULT_RATE_LIMIT_DECAY_SECONDS = 60;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function startTyping(string|int $conversationId, string|int $userId, array $payload): array
    {
        $expiresAt = now()->addSeconds($this->ttl('typing_seconds', self::DEFAULT_TYPING_TTL_SECONDS));
        $state = [
            ...$payload,
            'conversation_id' => (string) $conversationId,
            'user_id' => (string) $userId,
            'started_at' => $payload['started_at'] ?? now()->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ];

        $this->put($this->typingKey($conversationId, $userId), $state, $this->ttl('typing_seconds', self::DEFAULT_TYPING_TTL_SECONDS), [
            'cache_area' => 'chat_typing',
        ]);

        return $state;
    }

    public function stopTyping(string|int $conversationId, string|int $userId): bool
    {
        return $this->forget($this->typingKey($conversationId, $userId), [
            'cache_area' => 'chat_typing',
        ]);
    }

    public function typingState(string|int $conversationId, string|int $userId): ?array
    {
        $state = $this->get($this->typingKey($conversationId, $userId), null, [
            'cache_area' => 'chat_typing',
        ]);

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function setPresence(string|int $userId, string $state, array $metadata = []): array
    {
        $payload = [
            'user_id' => (string) $userId,
            'state' => $state,
            'metadata' => $metadata,
            'seen_at' => now()->toISOString(),
        ];

        $this->put($this->presenceKey($userId), $payload, $this->ttl('presence_seconds', self::DEFAULT_PRESENCE_TTL_SECONDS), [
            'cache_area' => 'chat_presence',
        ]);

        return $payload;
    }

    public function presence(string|int $userId): ?array
    {
        $presence = $this->get($this->presenceKey($userId), null, [
            'cache_area' => 'chat_presence',
        ]);

        return is_array($presence) ? $presence : null;
    }

    public function setActiveConversation(string|int $userId, string|int $conversationId): bool
    {
        return $this->put(
            $this->activeConversationKey($userId),
            [
                'user_id' => (string) $userId,
                'conversation_id' => (string) $conversationId,
                'active_at' => now()->toISOString(),
            ],
            $this->ttl('active_conversation_seconds', self::DEFAULT_ACTIVE_CONVERSATION_TTL_SECONDS),
            ['cache_area' => 'chat_active_conversation']
        );
    }

    public function activeConversation(string|int $userId): ?array
    {
        $state = $this->get($this->activeConversationKey($userId), null, [
            'cache_area' => 'chat_active_conversation',
        ]);

        return is_array($state) ? $state : null;
    }

    public function rememberUnreadCount(string|int $userId, Closure $callback): int
    {
        return (int) $this->remember(
            $this->unreadCountKey($userId),
            $this->ttl('unread_count_seconds', self::DEFAULT_UNREAD_COUNT_TTL_SECONDS),
            $callback,
            ['cache_area' => 'chat_unread_count']
        );
    }

    public function forgetUnreadCount(string|int $userId): bool
    {
        return $this->forget($this->unreadCountKey($userId), [
            'cache_area' => 'chat_unread_count',
        ]);
    }

    public function acquireDuplicateReminderLock(string|int $conversationId, string $reminderKey): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return $this->cache()->add(
                $this->duplicateReminderLockKey($conversationId, $reminderKey),
                true,
                $this->ttl('duplicate_reminder_lock_seconds', self::DEFAULT_DUPLICATE_REMINDER_LOCK_TTL_SECONDS)
            );
        } catch (Throwable $exception) {
            $this->logFailure('Chat duplicate reminder lock failed.', $exception, [
                'cache_area' => 'chat_duplicate_reminder_lock',
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordDeliveryStatus(string|int $messageId, string|int $recipientId, string $status, array $metadata = []): bool
    {
        return $this->put(
            $this->deliveryStatusKey($messageId, $recipientId),
            [
                'message_id' => (string) $messageId,
                'recipient_id' => (string) $recipientId,
                'status' => $status,
                'metadata' => $metadata,
                'recorded_at' => now()->toISOString(),
            ],
            $this->ttl('delivery_status_seconds', self::DEFAULT_DELIVERY_STATUS_TTL_SECONDS),
            ['cache_area' => 'chat_delivery_status']
        );
    }

    public function deliveryStatus(string|int $messageId, string|int $recipientId): ?array
    {
        $status = $this->get($this->deliveryStatusKey($messageId, $recipientId), null, [
            'cache_area' => 'chat_delivery_status',
        ]);

        return is_array($status) ? $status : null;
    }

    public function hitRateLimit(string $name, string|int $identifier, ?int $maxAttempts = null, ?int $decaySeconds = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $key = $this->rateLimitKey($name, $identifier);
        $maxAttempts ??= $this->rateLimitMaxAttempts($name);
        $decaySeconds ??= $this->ttl('rate_limit_seconds', self::DEFAULT_RATE_LIMIT_DECAY_SECONDS);

        try {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return true;
            }

            RateLimiter::hit($key, $decaySeconds);

            return false;
        } catch (Throwable $exception) {
            $this->logFailure('Chat rate limit helper failed.', $exception, [
                'cache_area' => 'chat_rate_limit',
            ]);

            return false;
        }
    }

    public function clearRateLimit(string $name, string|int $identifier): void
    {
        try {
            RateLimiter::clear($this->rateLimitKey($name, $identifier));
        } catch (Throwable $exception) {
            $this->logFailure('Chat rate limit clear failed.', $exception, [
                'cache_area' => 'chat_rate_limit',
            ]);
        }
    }

    public function typingKey(string|int $conversationId, string|int $userId): string
    {
        return $this->key('typing', $conversationId, $userId);
    }

    public function presenceKey(string|int $userId): string
    {
        return $this->key('presence', $userId);
    }

    public function activeConversationKey(string|int $userId): string
    {
        return $this->key('active_conversation', $userId);
    }

    public function unreadCountKey(string|int $userId): string
    {
        return $this->key('unread_count', $userId);
    }

    public function duplicateReminderLockKey(string|int $conversationId, string $reminderKey): string
    {
        return $this->key('duplicate_reminder_lock', $conversationId, $reminderKey);
    }

    public function deliveryStatusKey(string|int $messageId, string|int $recipientId): string
    {
        return $this->key('delivery_status', $messageId, $recipientId);
    }

    public function rateLimitKey(string $name, string|int $identifier): string
    {
        return $this->key('rate_limit', $name, $identifier);
    }

    private function remember(string $key, int $ttlSeconds, Closure $callback, array $context): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        try {
            return $this->cache()->remember($key, $ttlSeconds, $callback);
        } catch (Throwable $exception) {
            $this->logFailure('Chat cache remember failed; using source fallback.', $exception, $context);

            return $callback();
        }
    }

    private function get(string $key, mixed $default, array $context): mixed
    {
        if (! $this->enabled()) {
            return value($default);
        }

        try {
            return $this->cache()->get($key, $default);
        } catch (Throwable $exception) {
            $this->logFailure('Chat cache read failed; using fallback.', $exception, $context);

            return value($default);
        }
    }

    private function put(string $key, mixed $value, int $ttlSeconds, array $context): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return $this->cache()->put($key, $value, $ttlSeconds);
        } catch (Throwable $exception) {
            $this->logFailure('Chat cache write failed.', $exception, $context);

            return false;
        }
    }

    private function forget(string $key, array $context): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return $this->cache()->forget($key);
        } catch (Throwable $exception) {
            $this->logFailure('Chat cache invalidation failed.', $exception, $context);

            return false;
        }
    }

    private function cache(): Repository
    {
        $store = config('chat.realtime.store');

        return $store ? Cache::store((string) $store) : Cache::store();
    }

    private function enabled(): bool
    {
        return (bool) config('chat.realtime.enabled', true);
    }

    private function ttl(string $name, int $default): int
    {
        return max(1, (int) config("chat.realtime.ttl.{$name}", $default));
    }

    private function rateLimitMaxAttempts(string $name): int
    {
        return max(1, (int) config("chat.realtime.rate_limits.{$name}", 30));
    }

    private function key(string ...$segments): string
    {
        $prefix = trim((string) config('chat.realtime.namespace', 'tvio:chat'), ':');
        $environment = $this->keySegment((string) config('chat.realtime.environment', config('app.env', 'production')));

        return collect([$prefix, $environment, ...$segments])
            ->map(fn (string|int $segment, int $index): string => $index === 0 ? (string) $segment : $this->keySegment((string) $segment))
            ->implode(':');
    }

    private function keySegment(string $segment): string
    {
        $segment = trim($segment);

        return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $segment) ?: 'none';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $message, Throwable $exception, array $context): void
    {
        Log::warning($message, [
            ...$context,
            'failure_type' => $exception::class,
        ]);
    }
}
