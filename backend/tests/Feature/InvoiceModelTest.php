<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_stores_billing_amounts_dates_status_and_gateway_fields(): void
    {
        $student = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);
        $courseProgram = CourseProgram::factory()->create();
        $issuedDate = Carbon::parse('2026-05-01');
        $dueDate = Carbon::parse('2026-05-15');

        $invoice = Invoice::create([
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'course_program_id' => $courseProgram->id,
            'invoice_number' => 'INV-2026-0001',
            'amount' => 1000,
            'tax_amount' => 120,
            'total_amount' => 1120,
            'currency' => 'USD',
            'issued_date' => $issuedDate,
            'due_date' => $dueDate,
            'paid_date' => null,
            'status' => Invoice::STATUS_UNPAID,
            'payment_gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_123',
            'gateway_invoice_id' => 'in_test_123',
            'gateway_payment_intent_id' => 'pi_test_123',
            'gateway_checkout_session_id' => 'cs_test_123',
            'gateway_payment_method_id' => 'pm_test_123',
            'gateway_status' => 'draft',
            'payment_reference' => 'manual-reference-123',
            'gateway_payload' => [
                'mode' => 'placeholder',
            ],
            'metadata' => [
                'pdf_template' => 'standard',
            ],
        ]);

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'course_program_id' => $courseProgram->id,
            'invoice_number' => 'INV-2026-0001',
            'status' => Invoice::STATUS_UNPAID,
            'currency' => 'USD',
            'payment_gateway' => 'stripe',
            'gateway_customer_id' => 'cus_test_123',
        ]);

        $this->assertSame('1000.00', $invoice->amount);
        $this->assertSame('120.00', $invoice->tax_amount);
        $this->assertSame('1120.00', $invoice->total_amount);
        $this->assertTrue($invoice->issued_date->equalTo($issuedDate));
        $this->assertTrue($invoice->due_date->equalTo($dueDate));
        $this->assertSame('placeholder', $invoice->gateway_payload['mode']);
        $this->assertSame('standard', $invoice->metadata['pdf_template']);
    }

    public function test_invoice_relationships_support_student_billing_history(): void
    {
        $student = User::factory()->create();
        $studentProfile = StudentProfile::create([
            'user_id' => $student->id,
        ]);
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);
        $courseProgram = CourseProgram::factory()->create();

        $invoice = Invoice::factory()
            ->forSubscription($subscription)
            ->forCourseProgram($courseProgram)
            ->create([
                'student_id' => $student->id,
                'invoice_number' => 'INV-2026-0002',
            ]);

        $this->assertTrue($invoice->student->is($student));
        $this->assertTrue($invoice->subscription->is($subscription));
        $this->assertTrue($invoice->courseProgram->is($courseProgram));
        $this->assertTrue($student->studentInvoices->first()->is($invoice));
        $this->assertTrue($studentProfile->invoices->first()->is($invoice));
        $this->assertTrue($subscription->invoices->first()->is($invoice));
        $this->assertTrue($courseProgram->invoices->first()->is($invoice));
    }

    public function test_invoice_constants_match_supported_statuses(): void
    {
        $this->assertSame([
            Invoice::STATUS_PAID,
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_OVERDUE,
        ], Invoice::STATUSES);
    }
}
