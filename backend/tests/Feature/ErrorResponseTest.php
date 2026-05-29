<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unauthenticated_api_response_is_consistent(): void
    {
        $this->getJson('/api/v1/user')
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_forbidden_api_response_is_consistent(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $this->actingAs($student)->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertExactJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_unhandled_api_errors_do_not_expose_debug_details(): void
    {
        Route::get('/api/v1/test-unhandled-error', function (): void {
            throw new RuntimeException('SQLSTATE[HY000] /Users/john/Projects/www/dc/tutorvio-portal-x/backend/app/Models/User.php');
        });

        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/test-unhandled-error')
            ->assertStatus(500)
            ->assertExactJson([
                'message' => 'Server error.',
            ]);

        $encoded = json_encode($response->json());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SQLSTATE', $encoded);
        $this->assertStringNotContainsString('/Users/john', $encoded);
        $this->assertStringNotContainsString('User.php', $encoded);
        $this->assertStringNotContainsString('trace', $encoded);
    }
}
