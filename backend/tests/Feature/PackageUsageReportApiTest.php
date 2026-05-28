<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PackageUsageReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-05-26 10:00:00');

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_package_usage_totals_and_rows(): void
    {
        $student = $this->student(['name' => 'Ada Student']);
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-FALLBACK-001',
        ]);
        $subscription = Subscription::factory()->package()->create([
            'user_id' => $student->id,
            'plan_name' => 'Intensive 20 Lessons',
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 7,
            'remaining_lesson_count' => 13,
            'status' => Subscription::STATUS_ACTIVE,
            'is_frozen' => false,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_id' => $invoice->id,
            'invoice_reference' => null,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-30',
        ]);
        Subscription::factory()->frozen()->create([
            'user_id' => $this->student()->id,
            'plan_name' => 'Frozen Package',
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 10,
            'starts_at' => '2026-05-05',
            'ends_at' => '2026-06-30',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/package-usage')
            ->assertOk()
            ->assertJsonPath('data.summary.total_active_packages', 1)
            ->assertJsonPath('data.summary.total_consumed_lessons', 9)
            ->assertJsonPath('data.summary.total_remaining_lessons', 23)
            ->assertJsonPath('data.summary.expiring_package_count', 1)
            ->assertJsonPath('data.summary.frozen_package_count', 1)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $student->id)
            ->assertJsonPath('data.rows.0.student_name', 'Ada Student')
            ->assertJsonPath('data.rows.0.package_id', $subscription->id)
            ->assertJsonPath('data.rows.0.package_name', 'Intensive 20 Lessons')
            ->assertJsonPath('data.rows.0.package_status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.rows.0.lesson_balance', 20)
            ->assertJsonPath('data.rows.0.consumed_lessons', 7)
            ->assertJsonPath('data.rows.0.remaining_lessons', 13)
            ->assertJsonPath('data.rows.0.starts_at', '2026-05-01')
            ->assertJsonPath('data.rows.0.ends_at', '2026-05-30')
            ->assertJsonPath('data.rows.0.payment_status', Subscription::PAYMENT_STATUS_PARTIAL)
            ->assertJsonPath('data.rows.0.invoice_reference', 'INV-FALLBACK-001');
    }

    public function test_package_usage_report_filters_by_student_course_status_and_date_range(): void
    {
        $course = CourseProgram::factory()->create(['title' => 'Business English']);
        $student = $this->student(['name' => 'Filtered Student']);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $course->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);

        $matching = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Matching Package',
            'status' => Subscription::STATUS_ACTIVE,
            'consumed_lesson_count' => 3,
            'remaining_lesson_count' => 9,
            'starts_at' => '2026-05-15',
        ]);
        Subscription::factory()->create([
            'user_id' => $student->id,
            'status' => Subscription::STATUS_CANCELLED,
            'starts_at' => '2026-05-15',
        ]);
        Subscription::factory()->create([
            'user_id' => $this->student()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => '2026-04-15',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/reports/package-usage?student_id={$student->id}&course_id={$course->id}&status=active&date_from=2026-05-01&date_to=2026-05-31")
            ->assertOk()
            ->assertJsonPath('data.summary.total_active_packages', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.package_id', $matching->id)
            ->assertJsonPath('data.rows.0.course.id', $course->id)
            ->assertJsonPath('data.filters.student_id', $student->id)
            ->assertJsonPath('data.filters.course_id', $course->id)
            ->assertJsonPath('data.filters.status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.filters.date_from', '2026-05-01')
            ->assertJsonPath('data.filters.date_to', '2026-05-31');
    }

    public function test_package_usage_report_restricts_staff_access_and_billing_fields(): void
    {
        Subscription::factory()->create([
            'user_id' => $this->student()->id,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'invoice_reference' => 'INV-HIDDEN-FROM-REPORT-ONLY',
        ]);

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/reports/package-usage')
            ->assertForbidden()
            ->assertJsonMissing(['invoice_reference' => 'INV-HIDDEN-FROM-REPORT-ONLY']);

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/package-usage')
            ->assertOk()
            ->assertJsonMissingPath('data.rows.0.payment_status')
            ->assertJsonMissingPath('data.rows.0.invoice_reference')
            ->assertJsonMissing(['invoice_reference' => 'INV-HIDDEN-FROM-REPORT-ONLY']);

        $staff->givePermissionTo('invoices.view');
        $this->getJson('/api/v1/admin/reports/package-usage')
            ->assertOk()
            ->assertJsonPath('data.rows.0.payment_status', Subscription::PAYMENT_STATUS_OVERDUE)
            ->assertJsonPath('data.rows.0.invoice_reference', 'INV-HIDDEN-FROM-REPORT-ONLY');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function student(array $attributes = []): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE, ...$attributes]);
        $student->assignRole('student');

        return $student;
    }
}
