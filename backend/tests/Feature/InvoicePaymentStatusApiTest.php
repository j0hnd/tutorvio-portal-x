<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoicePaymentStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-05-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_update_invoice_payment_status(): void
    {
        $admin = $this->userWithRole('admin');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'status' => Invoice::STATUS_UNPAID,
            'paid_date' => null,
            'metadata' => ['source' => 'manual'],
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
            'paid_date' => '2026-05-20',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.paid_date', '2026-05-20T00:00:00.000000Z')
            ->assertJsonPath('data.metadata.source', 'manual')
            ->assertJsonPath('data.metadata.payment_status_history.0.from_status', Invoice::STATUS_UNPAID)
            ->assertJsonPath('data.metadata.payment_status_history.0.to_status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.metadata.payment_status_history.0.changed_by', $admin->id);

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame('2026-05-20', $invoice->paid_date->toDateString());
    }

    public function test_staff_with_billing_permission_can_update_invoice_payment_status(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('invoices.update');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($staff);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.metadata.payment_status_history.0.changed_by', $staff->id);
    }

    public function test_unauthorized_staff_is_blocked_from_updating_invoice_payment_status(): void
    {
        $staff = $this->userWithRole('staff');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($staff);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])->assertForbidden();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->refresh()->status);
    }

    public function test_student_is_blocked_from_updating_invoice_payment_status(): void
    {
        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create(['student_id' => $student->id]);

        Sanctum::actingAs($student);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])->assertForbidden();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->refresh()->status);
    }

    public function test_teacher_is_blocked_from_updating_invoice_payment_status(): void
    {
        $teacher = $this->userWithRole('teacher');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($teacher);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])->assertForbidden();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->refresh()->status);
    }

    public function test_paid_date_defaults_when_paid_and_clears_when_reverted_to_unpaid(): void
    {
        $admin = $this->userWithRole('admin');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'status' => Invoice::STATUS_OVERDUE,
            'paid_date' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.paid_date', '2026-05-26T00:00:00.000000Z');

        $this->assertSame('2026-05-26', $invoice->refresh()->paid_date->toDateString());

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_UNPAID,
            'paid_date' => '2026-05-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_UNPAID)
            ->assertJsonPath('data.paid_date', null)
            ->assertJsonPath('data.metadata.payment_status_history.1.from_status', Invoice::STATUS_PAID)
            ->assertJsonPath('data.metadata.payment_status_history.1.to_status', Invoice::STATUS_UNPAID)
            ->assertJsonPath('data.metadata.payment_status_history.1.from_paid_date', '2026-05-26')
            ->assertJsonPath('data.metadata.payment_status_history.1.to_paid_date', null);

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertNull($invoice->paid_date);
    }

    public function test_invalid_payment_status_transition_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $invoice = Invoice::factory()->paid()->create([
            'student_id' => $this->userWithRole('student')->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'status' => Invoice::STATUS_PAID,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
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
