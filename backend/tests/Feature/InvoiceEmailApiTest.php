<?php

namespace Tests\Feature;

use App\Jobs\Billing\SendInvoiceEmail;
use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\Notification as PortalNotification;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Billing\InvoiceEmailNotification;
use App\Services\Billing\InvoiceEmailService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceEmailApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        config([
            'billing.invoice.email.automatic_enabled' => false,
            'billing.tax.rate' => 0.12,
        ]);

        Carbon::setTestNow('2026-05-26 10:00:00');

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_manually_send_invoice_email_to_student(): void
    {
        Queue::fake();
        Notification::fake();

        $student = $this->student();
        $invoice = Invoice::factory()->forCourseProgram(CourseProgram::factory()->create())->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-MANUAL-EMAIL',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/invoices/{$invoice->public_id}/send-email")
            ->assertAccepted()
            ->assertJsonPath('email_queued', true);

        $this->postJson("/api/v1/invoices/{$invoice->public_id}/send-email")
            ->assertAccepted()
            ->assertJsonPath('email_queued', true);

        Queue::assertPushed(SendInvoiceEmail::class, 2);
        Queue::assertPushed(SendInvoiceEmail::class, function (SendInvoiceEmail $job) use ($invoice): bool {
            return $job->invoiceId === $invoice->id
                && $job->mode === InvoiceEmailService::MODE_MANUAL
                && $job->senderId === $this->admin->id;
        });
        Notification::assertNothingSent();

        $this->assertSame(0, PortalNotification::where('type', PortalNotification::TYPE_EMAIL)->count());
    }

    public function test_invoice_email_notification_attaches_pdf_invoice(): void
    {
        $student = $this->student();
        $invoice = Invoice::factory()->forCourseProgram(CourseProgram::factory()->create())->create([
            'student_id' => $student->id,
            'invoice_number' => 'INV-PDF-EMAIL',
        ]);

        $mail = (new InvoiceEmailNotification($invoice))->toMail($student);

        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('invoice-INV-PDF-EMAIL.pdf', $mail->rawAttachments[0]['name']);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
        $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
    }

    public function test_automatic_invoice_email_is_queued_after_generation_when_enabled(): void
    {
        Queue::fake();
        Notification::fake();
        config(['billing.invoice.email.automatic_enabled' => true]);

        Sanctum::actingAs($this->admin);

        $student = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);

        $response = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 100,
        ])->assertCreated();

        $invoice = Invoice::query()->where('public_id', $response->json('data.id'))->firstOrFail();

        Queue::assertPushed(SendInvoiceEmail::class, function (SendInvoiceEmail $job) use ($invoice): bool {
            return $job->invoiceId === $invoice->id
                && $job->mode === InvoiceEmailService::MODE_AUTOMATIC
                && $job->senderId === null;
        });
        Notification::assertNothingSent();
        $this->assertNull(data_get($invoice->metadata, 'email.last_status'));
        $this->assertSame(0, PortalNotification::where('type', PortalNotification::TYPE_EMAIL)->count());

        Queue::assertPushed(SendInvoiceEmail::class, 1);
    }

    public function test_automatic_invoice_email_is_not_sent_when_disabled(): void
    {
        Notification::fake();

        Sanctum::actingAs($this->admin);

        $student = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 100,
        ])->assertCreated();

        Notification::assertNothingSent();
        $this->assertSame(0, PortalNotification::where('type', PortalNotification::TYPE_EMAIL)->count());
    }

    private function student(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create();

        return $student;
    }
}
