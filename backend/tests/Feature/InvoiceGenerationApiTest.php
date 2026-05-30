<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\InvoiceGenerationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        config([
            'billing.default_currency' => null,
            'billing.currency' => 'USD',
            'billing.tax.label' => 'VAT',
            'billing.tax.rate' => 0.12,
            'billing.tax.default.country' => null,
            'billing.tax.default.label' => null,
            'billing.tax.default.rate' => null,
            'billing.tax.rules' => [],
            'billing.invoice.due_days' => 14,
        ]);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        Sanctum::actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_generate_subscription_invoice(): void
    {
        Carbon::setTestNow('2026-05-26 10:00:00');

        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Intensive',
        ]);

        $response = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 1000,
            'currency' => 'php',
            'issued_date' => '2026-05-26',
            'payment_reference' => 'manual-subscription-reference',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $student->public_id)
            ->assertJsonPath('data.subscription_id', $subscription->public_id)
            ->assertJsonPath('data.course_program_id', null)
            ->assertJsonPath('data.subtotal', '1000.00')
            ->assertJsonPath('data.tax_amount', '120.00')
            ->assertJsonPath('data.total_amount', '1120.00')
            ->assertJsonPath('data.currency', 'PHP')
            ->assertJsonPath('data.status', Invoice::STATUS_UNPAID)
            ->assertJsonPath('data.metadata.source', 'manual')
            ->assertJsonPath('data.metadata.tax_label', 'VAT')
            ->assertJsonPath('data.metadata.tax_rate', 0.12)
            ->assertJsonPath('data.metadata.generated_by', $this->admin->id)
            ->assertJsonPath('data.payment_reference', 'manual-subscription-reference')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invoice_number',
                    'reference',
                    'student',
                    'subscription',
                ],
            ]);

        $invoiceNumber = $response->json('data.invoice_number');

        $this->assertStringStartsWith('INV-20260526-', $invoiceNumber);
        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->id,
            'course_program_id' => null,
            'invoice_number' => $invoiceNumber,
            'amount' => 1000,
            'tax_amount' => 120,
            'total_amount' => 1120,
            'currency' => 'PHP',
            'status' => Invoice::STATUS_UNPAID,
        ]);
        $invoice = Invoice::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $audit = AuditLog::query()
            ->where('action_type', AuditActionType::PAYMENT_CREATED->value)
            ->where('module', AuditModule::BILLING->value)
            ->where('target_entity_type', 'invoice')
            ->where('target_entity_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($student->id, $audit->metadata['affected_user_id'] ?? null);
        $this->assertSame($subscription->id, $audit->metadata['subscription_id'] ?? null);
        $this->assertTrue(($audit->metadata['payment_reference_present'] ?? false) === true);
        $this->assertArrayNotHasKey('payment_reference', $audit->metadata);
        $this->assertArrayNotHasKey('card_number', $audit->metadata);
    }

    public function test_admin_can_generate_course_program_invoice(): void
    {
        $student = $this->student();
        $courseProgram = CourseProgram::factory()->create(['title' => 'Business English']);

        $response = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 250,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $student->public_id)
            ->assertJsonPath('data.subscription_id', null)
            ->assertJsonPath('data.course_program_id', $courseProgram->public_id)
            ->assertJsonPath('data.subtotal', '250.00')
            ->assertJsonPath('data.tax_amount', '30.00')
            ->assertJsonPath('data.total_amount', '280.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.course_program.title', 'Business English');
    }

    public function test_invoice_generation_uses_configured_default_currency_and_tax_profile(): void
    {
        config([
            'billing.default_currency' => 'php',
            'billing.tax.default.country' => 'PH',
            'billing.tax.default.label' => 'VAT',
            'billing.tax.default.rate' => 0.075,
        ]);

        $student = $this->student();
        $courseProgram = CourseProgram::factory()->create();

        $response = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 200,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tax_amount', '15.00')
            ->assertJsonPath('data.total_amount', '215.00')
            ->assertJsonPath('data.currency', 'PHP')
            ->assertJsonPath('data.metadata.tax_label', 'VAT')
            ->assertJsonPath('data.metadata.tax_rate', 0.075)
            ->assertJsonPath('data.metadata.tax_country', 'PH');

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'amount' => 200,
            'tax_amount' => 15,
            'total_amount' => 215,
            'currency' => 'PHP',
        ]);
    }

    public function test_invoice_generation_can_use_country_tax_rule_without_recalculating_old_invoices(): void
    {
        config([
            'billing.default_currency' => 'USD',
            'billing.tax.default.label' => 'VAT',
            'billing.tax.default.rate' => 0.1,
            'billing.tax.rules' => [
                'PH' => [
                    'label' => 'PH VAT',
                    'rate' => 0.12,
                ],
            ],
        ]);

        $student = $this->student();
        $courseProgram = CourseProgram::factory()->create();

        $firstResponse = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 100,
            'tax_country' => 'ph',
        ]);

        $firstResponse->assertCreated();
        $firstInvoice = Invoice::query()->where('public_id', $firstResponse->json('data.id'))->firstOrFail();

        config([
            'billing.default_currency' => 'EUR',
            'billing.tax.default.label' => 'VAT',
            'billing.tax.default.rate' => 0.2,
            'billing.tax.rules' => [],
        ]);

        $secondResponse = $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 100,
            'allow_duplicate' => true,
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('data.tax_amount', '20.00')
            ->assertJsonPath('data.total_amount', '120.00')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.metadata.tax_label', 'VAT')
            ->assertJsonPath('data.metadata.tax_rate', 0.2);

        $firstInvoice->refresh();

        $this->assertSame('100.00', $firstInvoice->amount);
        $this->assertSame('12.00', $firstInvoice->tax_amount);
        $this->assertSame('112.00', $firstInvoice->total_amount);
        $this->assertSame('USD', $firstInvoice->currency);
        $this->assertSame('PH VAT', $firstInvoice->metadata['tax_label']);
        $this->assertSame(0.12, $firstInvoice->metadata['tax_rate']);
        $this->assertSame('PH', $firstInvoice->metadata['tax_country']);
    }

    public function test_invoice_generation_prevents_duplicate_subscription_invoice_by_default(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 100,
        ])->assertCreated();

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice');

        $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
    }

    public function test_invoice_generation_allows_duplicate_when_explicitly_requested(): void
    {
        $student = $this->student();
        $courseProgram = CourseProgram::factory()->create();

        $payload = [
            'student_id' => $student->id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 100,
        ];

        $this->postJson('/api/v1/invoices/generate', $payload)->assertCreated();
        $this->postJson('/api/v1/invoices/generate', [
            ...$payload,
            'allow_duplicate' => true,
        ])->assertCreated();

        $this->assertSame(
            2,
            Invoice::where('student_id', $student->id)
                ->where('course_program_id', $courseProgram->id)
                ->count()
        );
    }

    public function test_invoice_generation_validates_student_and_source_relationships(): void
    {
        $student = $this->student();
        $otherStudent = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $otherStudent->id]);

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription_id');

        $nonStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $nonStudent->id,
            'course_program_id' => CourseProgram::factory()->create()->id,
            'subtotal' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_invoice_generation_requires_exactly_one_source(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);
        $courseProgram = CourseProgram::factory()->create();

        $this->postJson('/api/v1/invoices/generate', [
            'student_id' => $student->id,
            'subscription_id' => $subscription->public_id,
            'course_program_id' => $courseProgram->id,
            'subtotal' => 100,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_service_can_generate_invoice_for_subscription_purchase_trigger(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create(['user_id' => $student->id]);

        $invoice = app(InvoiceGenerationService::class)
            ->generateForSubscriptionPurchase($subscription, 500, 'eur');

        $this->assertSame($student->id, $invoice->student_id);
        $this->assertSame($subscription->id, $invoice->subscription_id);
        $this->assertSame('500.00', $invoice->amount);
        $this->assertSame('60.00', $invoice->tax_amount);
        $this->assertSame('560.00', $invoice->total_amount);
        $this->assertSame('EUR', $invoice->currency);
        $this->assertSame('purchase', $invoice->metadata['source']);
    }

    private function student(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create();

        return $student;
    }
}
