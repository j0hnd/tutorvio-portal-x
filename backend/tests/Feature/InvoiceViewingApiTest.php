<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceViewingApiTest extends TestCase
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

    public function test_student_can_view_own_visible_invoice_without_payment_metadata(): void
    {
        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-STUDENT-OWN',
            'payment_reference' => 'internal-payment-reference',
            'metadata' => ['generated_by' => 99],
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->public_id)
            ->assertJsonPath('data.student_id', $student->public_id)
            ->assertJsonMissingPath('data.payment_reference')
            ->assertJsonMissingPath('data.metadata');

        $this->getJson('/api/v1/invoices?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->public_id);
    }

    public function test_student_invoice_access_can_be_disabled(): void
    {
        config(['billing.invoice.student_visibility_enabled' => false]);

        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create(['student_id' => $student->id]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->getJson("/api/v1/invoices/{$invoice->public_id}")->assertForbidden();
    }

    public function test_student_is_blocked_from_other_students_invoice_and_history(): void
    {
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $invoice = Invoice::factory()->create(['student_id' => $otherStudent->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")->assertForbidden();
        $this->getJson("/api/v1/students/{$otherStudent->id}/invoices")->assertForbidden();
    }

    public function test_admin_can_view_all_invoice_details(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'payment_reference' => 'manual-reference',
            'metadata' => ['source' => 'manual'],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->public_id)
            ->assertJsonPath('data.payment_reference', 'manual-reference')
            ->assertJsonPath('data.metadata.source', 'manual');

        $this->getJson("/api/v1/students/{$student->public_id}/invoices")
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->public_id);
    }

    public function test_staff_with_billing_permission_can_view_invoices(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('invoices.view');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'payment_reference' => 'staff-visible-reference',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->public_id);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertOk()
            ->assertJsonPath('data.payment_reference', 'staff-visible-reference');
    }

    public function test_staff_without_billing_permission_is_blocked(): void
    {
        $staff = $this->userWithRole('staff');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->getJson("/api/v1/invoices/{$invoice->public_id}")->assertForbidden();
    }

    public function test_teacher_is_blocked_by_default(): void
    {
        $teacher = $this->userWithRole('teacher');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->getJson("/api/v1/invoices/{$invoice->public_id}")->assertForbidden();
    }

    public function test_teacher_with_invoice_permission_is_still_blocked_from_invoice_details(): void
    {
        $teacher = $this->userWithRole('teacher');
        $teacher->givePermissionTo('invoices.view');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'payment_reference' => 'teacher-must-not-see-payment-reference',
            'metadata' => ['internal_note' => 'teacher-hidden'],
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['payment_reference' => 'teacher-must-not-see-payment-reference'])
            ->assertJsonMissing(['internal_note' => 'teacher-hidden']);
    }

    public function test_invoice_list_filters_by_student_status_package_reference_date_and_overdue(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Premium Conversation',
        ]);
        $courseProgram = CourseProgram::factory()->create(['title' => 'Business English']);

        $matching = Invoice::factory()
            ->overdue()
            ->forSubscription($subscription)
            ->forCourseProgram($courseProgram)
            ->create([
                'invoice_number' => 'INV-FILTER-MATCH',
                'issued_date' => '2026-05-10',
                'status' => Invoice::STATUS_OVERDUE,
            ]);

        Invoice::factory()->create([
            'student_id' => $otherStudent->id,
            'invoice_number' => 'INV-FILTER-OTHER-STUDENT',
            'issued_date' => '2026-05-10',
            'status' => Invoice::STATUS_OVERDUE,
        ]);
        Invoice::factory()->paid()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-FILTER-PAID',
            'issued_date' => '2026-05-10',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/invoices?student_id='.$student->id.'&status=overdue&subscription_id='.$subscription->id.'&course_program_id='.$courseProgram->id.'&package=Conversation&reference=MATCH&date_from=2026-05-01&date_to=2026-05-31&overdue=true&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->public_id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        if ($role === 'student') {
            $user->studentProfile()->create();
        }

        return $user;
    }
}
