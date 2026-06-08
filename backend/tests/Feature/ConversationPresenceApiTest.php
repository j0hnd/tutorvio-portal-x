<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatRealtimeStateService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store as CacheStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConversationPresenceApiTest extends TestCase
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

        $this->admin = $this->userWithRole('admin');
        $this->teacher = $this->userWithRole('teacher');
        $this->student = $this->userWithRole('student');
        $this->otherStudent = $this->userWithRole('student');

        config([
            'chat.realtime.enabled' => true,
            'chat.realtime.store' => 'array',
            'chat.realtime.ttl.presence_seconds' => 5,
            'chat.realtime.ttl.active_conversation_seconds' => 5,
        ]);

        Cache::store('array')->flush();
    }

    public function test_user_presence_is_stored_with_ttl(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/conversations/presence', [
            'state' => 'online',
            'metadata' => ['client' => 'web'],
        ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->student->public_id)
            ->assertJsonPath('data.state', 'online')
            ->assertJsonPath('data.online', true);

        $key = app(ChatRealtimeStateService::class)->presenceKey($this->student->public_id);
        $this->assertTrue(Cache::store('array')->has($key));
        $this->assertSame('web', app(ChatRealtimeStateService::class)->presence($this->student->public_id)['metadata']['client']);

        $this->travel(6)->seconds();

        $this->assertFalse(Cache::store('array')->has($key));
        $this->assertNull(app(ChatRealtimeStateService::class)->presence($this->student->public_id));
    }

    public function test_active_conversation_state_is_stored_with_ttl_and_visible_to_participants(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/presence/active")
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->student->public_id)
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.state', 'recently_active')
            ->assertJsonPath('data.active_conversation.is_active', true);

        $service = app(ChatRealtimeStateService::class);
        $this->assertTrue(Cache::store('array')->has($service->presenceKey($this->student->public_id)));
        $this->assertTrue(Cache::store('array')->has($service->activeConversationKey($this->student->public_id)));

        Sanctum::actingAs($this->teacher);

        $participants = $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->json('data.participants');
        $studentParticipant = collect($participants)->firstWhere('user_id', $this->student->public_id);

        $this->assertSame('recently_active', $studentParticipant['presence']['state']);
        $this->assertTrue($studentParticipant['presence']['active_conversation']['is_active']);

        $this->travel(6)->seconds();

        $this->assertFalse(Cache::store('array')->has($service->activeConversationKey($this->student->public_id)));

        $participants = $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->json('data.participants');
        $studentParticipant = collect($participants)->firstWhere('user_id', $this->student->public_id);

        $this->assertNull($studentParticipant['presence']['state']);
        $this->assertFalse($studentParticipant['presence']['active_conversation']['is_active']);
    }

    public function test_unauthorized_users_cannot_view_presence_metadata(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);
        $this->postJson('/api/v1/conversations/presence', ['state' => 'online'])
            ->assertOk();

        Sanctum::actingAs($this->otherStudent);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertNotFound();

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.participants.0.presence', null)
            ->assertJsonPath('data.participants.1.presence', null);
    }

    public function test_redis_unavailable_falls_back_without_breaking_presence_or_conversation_detail(): void
    {
        $this->useFailingChatCacheStore();
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/conversations/presence', ['state' => 'online'])
            ->assertOk()
            ->assertJsonPath('data.state', 'online');

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/presence/active")
            ->assertOk()
            ->assertJsonPath('data.active_conversation.is_active', false);

        $participants = $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->json('data.participants');
        $studentParticipant = collect($participants)->firstWhere('user_id', $this->student->public_id);

        $this->assertFalse($studentParticipant['presence']['online']);
        $this->assertFalse($studentParticipant['presence']['active_conversation']['is_active']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, User>  $participants
     */
    private function createConversation(array $participants): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ]);

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
