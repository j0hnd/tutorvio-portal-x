<?php

namespace Tests\Unit;

use App\Services\ChatRealtimeStateService;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store as CacheStore;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class ChatRealtimeStateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chat.realtime.enabled' => true,
            'chat.realtime.store' => 'array',
            'chat.realtime.namespace' => 'tvio:chat',
            'chat.realtime.environment' => 'testing',
            'chat.realtime.ttl.typing_seconds' => 5,
            'chat.realtime.ttl.presence_seconds' => 30,
            'chat.realtime.ttl.active_conversation_seconds' => 30,
            'chat.realtime.ttl.unread_count_seconds' => 30,
            'chat.realtime.ttl.duplicate_reminder_lock_seconds' => 30,
            'chat.realtime.ttl.delivery_status_seconds' => 30,
        ]);

        Cache::store('array')->flush();
    }

    public function test_it_namespaces_chat_realtime_keys_by_environment(): void
    {
        $service = app(ChatRealtimeStateService::class);

        $this->assertSame('tvio:chat:testing:typing:cnv_123:usr_456', $service->typingKey('cnv_123', 'usr_456'));
        $this->assertSame('tvio:chat:testing:presence:usr_456', $service->presenceKey('usr_456'));
        $this->assertSame('tvio:chat:testing:unread_count:usr_456', $service->unreadCountKey('usr_456'));
        $this->assertSame('tvio:chat:testing:unread_count:usr_456:conversation:cnv_123', $service->conversationUnreadCountKey('usr_456', 'cnv_123'));
    }

    public function test_it_tracks_transient_chat_state_without_message_history(): void
    {
        $service = app(ChatRealtimeStateService::class);

        $typing = $service->startTyping('cnv_123', 'usr_456', []);
        $this->assertSame('cnv_123', $typing['conversation_id']);
        $this->assertNotNull($service->typingState('cnv_123', 'usr_456'));

        $presence = $service->setPresence('usr_456', 'online', ['device' => 'web']);
        $this->assertSame('online', $presence['state']);
        $this->assertSame('web', $service->presence('usr_456')['metadata']['device']);

        $this->assertTrue($service->setActiveConversation('usr_456', 'cnv_123'));
        $this->assertSame('cnv_123', $service->activeConversation('usr_456')['conversation_id']);

        $this->assertSame(7, $service->rememberUnreadCount('usr_456', fn () => 7));
        $this->assertSame(7, $service->rememberUnreadCount('usr_456', fn () => 99));
        $this->assertSame(3, $service->rememberConversationUnreadCount('usr_456', 'cnv_123', fn () => 3));
        $this->assertSame(3, $service->rememberConversationUnreadCount('usr_456', 'cnv_123', fn () => 99));

        $this->assertTrue($service->recordDeliveryStatus('msg_123', 'usr_456', 'delivered'));
        $this->assertSame('delivered', $service->deliveryStatus('msg_123', 'usr_456')['status']);
    }

    public function test_duplicate_reminder_locks_are_atomic_ttl_records(): void
    {
        $service = app(ChatRealtimeStateService::class);

        $this->assertTrue($service->acquireDuplicateReminderLock('cnv_123', 'lesson-reminder'));
        $this->assertFalse($service->acquireDuplicateReminderLock('cnv_123', 'lesson-reminder'));
        $this->assertTrue($service->acquireDuplicateReminderLock('cnv_123', 'another-reminder'));
    }

    public function test_disabled_realtime_state_falls_back_without_cache_writes(): void
    {
        config(['chat.realtime.enabled' => false]);

        $service = app(ChatRealtimeStateService::class);

        $this->assertSame(12, $service->rememberUnreadCount('usr_456', fn () => 12));
        $this->assertFalse($service->setActiveConversation('usr_456', 'cnv_123'));
        $this->assertFalse($service->acquireDuplicateReminderLock('cnv_123', 'lesson-reminder'));
        $this->assertNull($service->typingState('cnv_123', 'usr_456'));
    }

    public function test_redis_backed_realtime_failures_are_best_effort(): void
    {
        $this->useFailingChatCacheStore();

        $service = app(ChatRealtimeStateService::class);

        $typing = $service->startTyping('cnv_123', 'usr_456', []);
        $this->assertSame('cnv_123', $typing['conversation_id']);
        $this->assertNull($service->typingState('cnv_123', 'usr_456'));
        $this->assertFalse($service->stopTyping('cnv_123', 'usr_456'));

        $presence = $service->setPresence('usr_456', 'online');
        $this->assertSame('online', $presence['state']);
        $this->assertNull($service->presence('usr_456'));

        $this->assertFalse($service->setActiveConversation('usr_456', 'cnv_123'));
        $this->assertSame(7, $service->rememberUnreadCount('usr_456', fn () => 7));
        $this->assertSame(5, $service->rememberConversationUnreadCount('usr_456', 'cnv_123', fn () => 5));
        $this->assertFalse($service->forgetUnreadCount('usr_456'));
        $this->assertFalse($service->forgetConversationUnreadCount('usr_456', 'cnv_123'));
        $this->assertFalse($service->recordDeliveryStatus('msg_123', 'usr_456', 'delivered'));
        $this->assertNull($service->deliveryStatus('msg_123', 'usr_456'));
        $this->assertFalse($service->acquireDuplicateReminderLock('cnv_123', 'lesson-reminder'));

        config(['cache.default' => 'chat_failing']);
        $service->clearRateLimit('typing', 'usr_456');

        $this->addToAssertionCount(1);
    }

    private function useFailingChatCacheStore(): void
    {
        Cache::extend('chat_failing', fn (): CacheRepository => new CacheRepository(new class implements CacheStore
        {
            public function get($key): mixed
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function many(array $keys): array
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function put($key, $value, $seconds): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function putMany(array $values, $seconds): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function increment($key, $value = 1): int|bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function decrement($key, $value = 1): int|bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function forever($key, $value): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function touch($key, $seconds): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function forget($key): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function flush(): bool
            {
                throw new RuntimeException('Redis unavailable');
            }

            public function getPrefix(): string
            {
                return '';
            }
        }));

        config([
            'cache.stores.chat_failing' => ['driver' => 'chat_failing'],
            'chat.realtime.store' => 'chat_failing',
        ]);
    }
}
