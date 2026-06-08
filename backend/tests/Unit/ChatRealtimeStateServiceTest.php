<?php

namespace Tests\Unit;

use App\Services\ChatRealtimeStateService;
use Illuminate\Support\Facades\Cache;
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
}
