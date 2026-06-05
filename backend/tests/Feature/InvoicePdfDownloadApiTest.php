<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\InvoicePdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoicePdfDownloadApiTest extends TestCase
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

    public function test_admin_can_download_invoice_pdf(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student', [
            'name' => 'Jane Student',
            'email' => 'jane@example.test',
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Intensive English',
        ]);
        $invoice = Invoice::factory()
            ->paid()
            ->forSubscription($subscription)
            ->create([
                'invoice_number' => 'INV-PDF-0001',
                'amount' => 1000,
                'tax_amount' => 120,
                'total_amount' => 1120,
                'currency' => 'USD',
                'issued_date' => '2026-05-01',
                'due_date' => '2026-05-15',
                'paid_date' => '2026-05-10',
                'metadata' => ['tax_label' => 'VAT'],
            ]);

        Sanctum::actingAs($admin);

        $response = $this->get("/api/v1/invoices/{$invoice->public_id}/download");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-INV-PDF-0001.pdf"');

        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('INV-PDF-0001', $content);
        $this->assertStringContainsString('Jane Student', $content);
        $this->assertStringContainsString('Intensive English', $content);
        $this->assertStringContainsString('USD 1,120.00', $content);
        $this->assertStringContainsString('Paid', $content);
        $this->assertStringContainsString('2026-05-10', $content);
    }

    public function test_student_can_download_own_invoice_when_visibility_is_enabled(): void
    {
        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-STUDENT-PDF',
        ]);

        Sanctum::actingAs($student);

        $this->get("/api/v1/invoices/{$invoice->public_id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_student_cannot_download_other_students_invoice_or_when_visibility_is_disabled(): void
    {
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $invoice = Invoice::factory()->create(['student_id' => $otherStudent->id]);

        Sanctum::actingAs($student);

        $this->get("/api/v1/invoices/{$invoice->public_id}/download")->assertForbidden();

        config(['billing.invoice.student_visibility_enabled' => false]);

        $ownInvoice = Invoice::factory()->create(['student_id' => $student->id]);

        $this->get("/api/v1/invoices/{$ownInvoice->public_id}/download")->assertForbidden();
    }

    public function test_staff_needs_billing_permission_to_download_invoice_pdf(): void
    {
        $staff = $this->userWithRole('staff');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($staff);

        $this->get("/api/v1/invoices/{$invoice->public_id}/download")->assertForbidden();

        $staff->givePermissionTo('invoices.view');

        $this->get("/api/v1/invoices/{$invoice->public_id}/download")->assertOk();
    }

    public function test_teacher_is_blocked_from_invoice_pdf_download_by_default(): void
    {
        $teacher = $this->userWithRole('teacher');
        $invoice = Invoice::factory()->create(['student_id' => $this->userWithRole('student')->id]);

        Sanctum::actingAs($teacher);

        $this->get("/api/v1/invoices/{$invoice->public_id}/download")->assertForbidden();
    }

    public function test_pdf_render_failure_returns_user_safe_error(): void
    {
        $admin = $this->userWithRole('admin');
        $invoice = Invoice::factory()->create([
            'student_id' => $this->userWithRole('student')->id,
            'invoice_number' => 'INV-PDF-FAIL',
        ]);

        $pdfs = Mockery::mock(InvoicePdfService::class);
        $pdfs->shouldReceive('render')
            ->once()
            ->with(Mockery::on(fn (Invoice $renderedInvoice): bool => $renderedInvoice->is($invoice)))
            ->andThrow(new RuntimeException('dompdf /Users/john/private-template failed'));
        app()->instance(InvoicePdfService::class, $pdfs);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/invoices/{$invoice->public_id}/download")
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'The invoice PDF could not be generated. Please try again later.',
            ]);

        $encoded = json_encode($response->json());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('dompdf', $encoded);
        $this->assertStringNotContainsString('/Users/john', $encoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['status' => User::STATUS_ACTIVE], $attributes));
        $user->assignRole($role);

        if ($role === 'student') {
            $user->studentProfile()->create();
        }

        return $user;
    }
}
