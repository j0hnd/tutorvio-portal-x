<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
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
        config(['billing.package_history.student_visibility_enabled' => true]);
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

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
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

        $this->getJson("/api/v1/students/{$otherStudent->public_id}/package-summary")
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

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertOk()
            ->assertJsonMissingPath('data.payment_status')
            ->assertJsonMissingPath('data.invoice_reference');
    }

    public function test_assigned_teacher_can_view_limited_student_package_balance_without_billing_fields(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);

        Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Assigned Student Package',
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 5,
            'remaining_lesson_count' => 7,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'invoice_reference' => 'INV-TEACHER-HIDDEN',
            'internal_notes' => 'Teacher must not see this.',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data.plan_name', 'Assigned Student Package')
            ->assertJsonPath('data.total_lessons', 12)
            ->assertJsonPath('data.consumed_lessons', 5)
            ->assertJsonPath('data.remaining_lessons', 7)
            ->assertJsonMissingPath('data.payment_status')
            ->assertJsonMissingPath('data.invoice_reference')
            ->assertJsonMissingPath('data.invoice_id')
            ->assertJsonMissingPath('data.internal_notes')
            ->assertJsonMissing(['invoice_reference' => 'INV-TEACHER-HIDDEN'])
            ->assertJsonMissing(['internal_notes' => 'Teacher must not see this.']);
    }

    public function test_teacher_cannot_view_unassigned_student_package_summary(): void
    {
        $teacher = $this->teacher();
        $otherTeacher = $this->teacher();
        $student = $this->student();
        $student->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        Subscription::factory()->create([
            'user_id' => $student->id,
            'invoice_reference' => 'INV-UNASSIGNED-HIDDEN',
            'internal_notes' => 'Unassigned teacher must not see this.',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertForbidden()
            ->assertJsonMissing(['invoice_reference' => 'INV-UNASSIGNED-HIDDEN'])
            ->assertJsonMissing(['internal_notes' => 'Unassigned teacher must not see this.']);
    }

    public function test_frozen_package_uses_frozen_student_status(): void
    {
        $student = $this->student();
        Subscription::factory()->frozen()->create(['user_id' => $student->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');
    }

    public function test_student_without_package_gets_null_summary(): void
    {
        $student = $this->student();

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->public_id}/package-summary")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_student_can_view_safe_own_package_history_when_enabled(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'invoice_reference' => 'INV-HISTORY-HIDDEN',
            'internal_notes' => 'Never show student.',
        ]);

        SubscriptionHistory::factory()->forSubscription($subscription)->create([
            'event_type' => SubscriptionHistory::EVENT_ASSIGNED,
            'notes' => 'Internal assignment note.',
            'previous_values' => ['internal_notes' => 'before'],
            'new_values' => [
                'invoice_reference' => 'INV-HISTORY-HIDDEN',
                'internal_notes' => 'Never show student.',
            ],
            'created_by' => User::factory()->create()->id,
        ]);
        SubscriptionHistory::factory()->forSubscription($subscription)->create([
            'event_type' => SubscriptionHistory::EVENT_PAYMENT_CHANGED,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'previous_values' => ['payment_status' => Subscription::PAYMENT_STATUS_UNPAID],
            'new_values' => ['payment_status' => Subscription::PAYMENT_STATUS_OVERDUE],
        ]);
        SubscriptionHistory::factory()->forSubscription($subscription)->create([
            'event_type' => SubscriptionHistory::EVENT_INVOICE_REFERENCE_CHANGED,
            'new_values' => ['invoice_reference' => 'INV-HISTORY-HIDDEN'],
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->public_id}/package-history")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', SubscriptionHistory::EVENT_ASSIGNED)
            ->assertJsonMissingPath('data.0.payment_status')
            ->assertJsonMissingPath('data.0.previous_values')
            ->assertJsonMissingPath('data.0.new_values')
            ->assertJsonMissingPath('data.0.notes')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissing(['event_type' => SubscriptionHistory::EVENT_PAYMENT_CHANGED])
            ->assertJsonMissing(['event_type' => SubscriptionHistory::EVENT_INVOICE_REFERENCE_CHANGED])
            ->assertJsonMissing(['invoice_reference' => 'INV-HISTORY-HIDDEN'])
            ->assertJsonMissing(['internal_notes' => 'Never show student.'])
            ->assertJsonMissing(['notes' => 'Internal assignment note.']);
    }

    public function test_student_package_history_visibility_can_be_disabled(): void
    {
        config(['billing.package_history.student_visibility_enabled' => false]);

        $student = $this->student();
        SubscriptionHistory::factory()->create(['student_id' => $student->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$student->public_id}/package-history")
            ->assertForbidden();
    }

    public function test_student_cannot_view_another_students_package_history(): void
    {
        $student = $this->student();
        $otherStudent = $this->student();
        SubscriptionHistory::factory()->create([
            'student_id' => $otherStudent->id,
            'notes' => 'Other private note.',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/students/{$otherStudent->public_id}/package-history")
            ->assertForbidden()
            ->assertJsonMissing(['notes' => 'Other private note.']);
    }

    public function test_staff_package_history_access_depends_on_billing_permission(): void
    {
        $student = $this->student();
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        SubscriptionHistory::factory()->create([
            'student_id' => $student->id,
            'event_type' => SubscriptionHistory::EVENT_PAYMENT_CHANGED,
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
            'notes' => 'Staff billing note.',
            'new_values' => ['payment_status' => Subscription::PAYMENT_STATUS_PAID],
            'created_by' => $staff->id,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/students/{$student->public_id}/package-history")
            ->assertForbidden()
            ->assertJsonMissing(['notes' => 'Staff billing note.']);

        $staff->givePermissionTo('subscriptions.view');

        $this->getJson("/api/v1/students/{$student->public_id}/package-history")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', SubscriptionHistory::EVENT_PAYMENT_CHANGED)
            ->assertJsonPath('data.0.payment_status', Subscription::PAYMENT_STATUS_PAID)
            ->assertJsonPath('data.0.notes', 'Staff billing note.')
            ->assertJsonPath('data.0.created_by', $staff->id)
            ->assertJsonPath('data.0.new_values.payment_status', Subscription::PAYMENT_STATUS_PAID);
    }

    private function student(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        return $student;
    }

    private function teacher(): User
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        return $teacher;
    }
}
