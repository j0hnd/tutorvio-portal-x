<?php

namespace Tests\Feature;

use App\Events\Messages\ConversationTypingStateChanged;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatRealtimeStateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store as CacheStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class ConversationTypingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        Cache::flush();

        $this->admin = $this->userWithRole('admin');
        $this->teacher = $this->userWithRole('teacher');
        $this->student = $this->userWithRole('student');
        $this->otherStudent = $this->userWithRole('student');

        config([
            'chat.realtime.store' => 'array',
            'chat.realtime.ttl.typing_seconds' => 5,
        ]);
    }

    public function test_active_participant_can_start_and_stop_typing_without_persistent_records(): void
    {
        Event::fake([ConversationTypingStateChanged::class]);
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $response = $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.user_id', $this->student->public_id)
            ->assertJsonPath('data.is_typing', true);

        $expiresAt = $response->json('data.expires_at');
        $this->assertNotNull($expiresAt);
        $this->assertTrue(Cache::has($this->cacheKey($conversation, $this->student)));

        Event::assertDispatched(
            ConversationTypingStateChanged::class,
            fn (ConversationTypingStateChanged $event): bool => $event->conversation->is($conversation)
                && $event->actor->is($this->student)
                && $event->isTyping === true
                && $event->expiresAt === $expiresAt
                && $event->broadcastAs() === 'conversation.typing'
                && $event->broadcastWith()['conversation_id'] === $conversation->public_id
                && $event->broadcastWith()['user_id'] === $this->student->public_id
        );

        $this->assertDatabaseCount('conversation_messages', 0);

        $this->travel(6)->seconds();
        $this->assertFalse(Cache::has($this->cacheKey($conversation, $this->student)));

        $this->deleteJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.user_id', $this->student->public_id)
            ->assertJsonPath('data.is_typing', false)
            ->assertJsonPath('data.expires_at', null);

        Event::assertDispatched(
            ConversationTypingStateChanged::class,
            fn (ConversationTypingStateChanged $event): bool => $event->conversation->is($conversation)
                && $event->actor->is($this->student)
                && $event->isTyping === false
                && $event->expiresAt === null
        );
    }

    public function test_authorized_participant_can_get_current_typing_users(): void
    {
        Event::fake([ConversationTypingStateChanged::class]);
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);
        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk();

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.typing_users.0.user_id', $this->student->public_id)
            ->assertJsonPath('data.typing_users.0.name', $this->student->name)
            ->assertJsonPath('data.typing_users.0.is_typing', true)
            ->assertJsonMissingPath('data.typing_users.1');
    }

    public function test_stopped_typing_clears_redis_state_before_ttl_expires(): void
    {
        Event::fake([ConversationTypingStateChanged::class]);
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk();
        $this->assertTrue(Cache::has($this->cacheKey($conversation, $this->student)));

        $this->deleteJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.is_typing', false);

        $this->assertFalse(Cache::has($this->cacheKey($conversation, $this->student)));

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.typing_users', []);
    }

    public function test_only_active_conversation_participants_can_send_typing_events(): void
    {
        Event::fake([ConversationTypingStateChanged::class]);
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->otherStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing/start")
            ->assertNotFound();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing/start")
            ->assertForbidden();

        $conversation->participants()->where('user_id', $this->student->id)->update(['archived_at' => now()]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing/start")
            ->assertNotFound();

        $closedConversation = $this->createConversation([$this->student, $this->teacher], [
            'status' => Conversation::STATUS_CLOSED,
        ]);

        $this->postJson("/api/v1/conversations/{$closedConversation->public_id}/typing/start")
            ->assertForbidden();

        Event::assertNotDispatched(ConversationTypingStateChanged::class);
    }

    public function test_only_active_conversation_participants_can_view_typing_state(): void
    {
        Event::fake([ConversationTypingStateChanged::class]);
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);
        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk();

        Sanctum::actingAs($this->otherStudent);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertNotFound();

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertForbidden();

        $conversation->participants()->where('user_id', $this->teacher->id)->update(['archived_at' => now()]);

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertNotFound();
    }

    public function test_typing_broadcast_channel_authorizes_active_participants_only(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        $this->assertNull($this->verifyBroadcastChannelAccess($this->student, $conversation));

        try {
            $this->verifyBroadcastChannelAccess($this->otherStudent, $conversation);
            $this->fail('Non-participants should not be authorized for the typing channel.');
        } catch (AccessDeniedHttpException) {
            $this->addToAssertionCount(1);
        }

        $conversation->participants()->where('user_id', $this->student->id)->update(['archived_at' => now()]);

        try {
            $this->verifyBroadcastChannelAccess($this->student, $conversation);
            $this->fail('Archived participants should not be authorized for the typing channel.');
        } catch (AccessDeniedHttpException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_typing_state_is_skipped_when_realtime_cache_is_unavailable(): void
    {
        $this->useFailingChatCacheStore();
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing/start")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.user_id', $this->student->public_id)
            ->assertJsonPath('data.is_typing', true);

        $this->assertNull(app(ChatRealtimeStateService::class)->typingState($conversation->public_id, $this->student->public_id));

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/typing")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.typing_users', []);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/typing/stop")
            ->assertOk()
            ->assertJsonPath('data.is_typing', false);
    }

    private function userWithRole(string $role, string $status = User::STATUS_ACTIVE): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, User>  $participants
     * @param  array<string, mixed>  $attributes
     */
    private function createConversation(array $participants, array $attributes = []): Conversation
    {
        $conversation = Conversation::query()->create(array_merge([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ], $attributes));

        foreach ($participants as $participant) {
            $role = $participant->roles->pluck('name')->first();

            $conversation->participants()->create([
                'user_id' => $participant->id,
                'participant_role' => $role,
                'participant_role_snapshot' => $role,
                'participant_roles_snapshot' => $participant->roles->pluck('name')->values()->all(),
                'joined_at' => now(),
            ]);
        }

        return $conversation;
    }

    private function cacheKey(Conversation $conversation, User $actor): string
    {
        return app(ChatRealtimeStateService::class)->typingKey($conversation->public_id, $actor->public_id);
    }

    private function verifyBroadcastChannelAccess(User $actor, Conversation $conversation): mixed
    {
        $request = Request::create('/api/v1/broadcasting/auth', 'POST');
        $request->setUserResolver(fn () => $actor);

        $broadcaster = Broadcast::connection();
        $verifier = new \ReflectionMethod($broadcaster, 'verifyUserCanAccessChannel');
        $verifier->setAccessible(true);

        return $verifier->invoke($broadcaster, $request, 'conversations.'.$conversation->public_id);
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
