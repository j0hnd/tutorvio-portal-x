<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentPackageSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        config(['billing.invoice.student_visibility_enabled' => true]);
        Carbon::setTestNow('2026-05-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_can_view_own_package_summary_without_admin_notes(): void
    {
        $student = $this->student();
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-STUDENT-PACKAGE',
        ]);
        $subscription = Subscription::factory()->package()->create([
            'user_id' => $student->id,
            'plan_name' => 'Intensive 20 Lessons',
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 7,
            'remaining_lesson_count' => 13,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_id' => $invoice->id,
            'invoice_reference' => 'INV-SAFE-REF',
            'internal_notes' => 'Do not expose this.',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-01',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data.name', 'Intensive 20 Lessons')
            ->assertJsonPath('data.plan_name', 'Intensive 20 Lessons')
            ->assertJsonPath('data.status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.total_lessons', 20)
            ->assertJsonPath('data.consumed_lessons', 7)
            ->assertJsonPath('data.remaining_lessons', 13)
            ->assertJsonPath('data.payment_status', Subscription::PAYMENT_STATUS_PARTIAL)
            ->assertJsonPath('data.invoice_reference', 'INV-SAFE-REF')
            ->assertJsonPath('data.renewal_reminder.reminder_date', '2026-05-25')
            ->assertJsonPath('data.renewal_reminder.days_until_end', 6)
            ->assertJsonPath('data.renewal_reminder.should_renew_soon', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.student_id')
            ->assertJsonMissingPath('data.package_type')
            ->assertJsonMissingPath('data.invoice_id')
            ->assertJsonMissingPath('data.internal_notes')
            ->assertJsonMissing(['internal_notes' => 'Do not expose this.']);

        $this->assertSame($subscription->id, Subscription::first()->id);
    }

    public function test_student_cannot_view_another_students_package_summary(): void
    {
        $student = $this->student();
        $otherStudent = $this->student();

        Subscription::factory()->create([
            'user_id' => $otherStudent->id,
            'internal_notes' => 'Other student private note.',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$otherStudent->id}/package-summary")
            ->assertForbidden()
            ->assertJsonMissing(['internal_notes' => 'Other student private note.']);
    }

    public function test_student_billing_fields_follow_invoice_visibility_rule(): void
    {
        config(['billing.invoice.student_visibility_enabled' => false]);

        $student = $this->student();
        Subscription::factory()->create([
            'user_id' => $student->id,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'invoice_reference' => 'INV-HIDDEN',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
            ->assertOk()
            ->assertJsonMissingPath('data.payment_status')
            ->assertJsonMissingPath('data.invoice_reference');
    }

    public function test_frozen_package_uses_frozen_student_status(): void
    {
        $student = $this->student();
        Subscription::factory()->frozen()->create(['user_id' => $student->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');
    }

    public function test_student_without_package_gets_null_summary(): void
    {
        $student = $this->student();

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    private function student(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        return $student;
    }
}
