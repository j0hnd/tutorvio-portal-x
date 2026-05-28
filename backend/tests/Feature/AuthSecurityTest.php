<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_returns_generic_error_message_for_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing-user@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_non_active_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'status' => User::STATUS_SUSPENDED,
        ]);
        $user->assignRole('student');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_successful_login_returns_token_without_internal_fields_and_sets_expiration(): void
    {
        Carbon::setTestNow('2026-05-28 10:00:00');
        config(['sanctum.expiration' => 120]);

        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->assignRole('student');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'active@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_at', '2026-05-28T12:00:00+00:00')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('status');

        $this->assertNotNull($user->fresh()->tokens()->latest('id')->value('expires_at'));

        Carbon::setTestNow();
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'rate-limit@example.com',
            'password' => 'password',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->assignRole('student');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_forgot_password_returns_generic_response(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'message' => 'If the account exists, a reset link has been sent.',
            ]);
    }
}
