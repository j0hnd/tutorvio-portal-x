<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_database_seeder_creates_default_admin_user_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Admin User', $admin->name);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($admin->activated_at);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasRole('admin'));
    }
}
