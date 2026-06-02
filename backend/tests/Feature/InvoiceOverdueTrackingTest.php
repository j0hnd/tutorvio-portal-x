<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceOverdueService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceOverdueTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-27 00:30:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_service_marks_only_unpaid_past_due_invoices_overdue(): void
    {
        $student = $this->userWithRole('student');
        $pastDue = Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-26',
            'paid_date' => null,
        ]);
        $dueToday = Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-27',
            'paid_date' => null,
        ]);
        $paidPastDue = Invoice::factory()->paid()->create([
            'student_id' => $student->id,
            'due_date' => '2026-05-20',
        ]);
        $unpaidWithPaidDate = Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-20',
            'paid_date' => '2026-05-21',
        ]);

        $this->assertSame(1, app(InvoiceOverdueService::class)->markOverdue());

        $this->assertSame(Invoice::STATUS_OVERDUE, $pastDue->refresh()->status);
        $this->assertSame(Invoice::STATUS_UNPAID, $dueToday->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $paidPastDue->refresh()->status);
        $this->assertSame(Invoice::STATUS_UNPAID, $unpaidWithPaidDate->refresh()->status);
    }

    public function test_command_marks_overdue_invoices(): void
    {
        $student = $this->userWithRole('student');
        $invoice = Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-26',
            'paid_date' => null,
        ]);

        $this->artisan('invoices:mark-overdue')
            ->expectsOutput('Marked 1 invoice(s) overdue.')
            ->assertExitCode(0);

        $this->assertSame(Invoice::STATUS_OVERDUE, $invoice->refresh()->status);
    }

    public function test_overdue_filter_includes_unpaid_past_due_invoices_without_paid_invoices(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $pastDue = Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-26',
            'paid_date' => null,
        ]);
        Invoice::factory()->paid()->create([
            'student_id' => $student->id,
            'due_date' => '2026-05-20',
        ]);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-27',
            'paid_date' => null,
        ]);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-20',
            'paid_date' => '2026-05-21',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/invoices?overdue=true&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pastDue->public_id);
    }

    public function test_model_calculates_overdue_in_app_timezone(): void
    {
        $invoice = Invoice::factory()->make([
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => '2026-05-26',
            'paid_date' => null,
        ]);

        $this->assertTrue($invoice->isOverdueAsOf(Carbon::parse('2026-05-27 00:30:00', 'Asia/Manila')));
        $this->assertFalse($invoice->isOverdueAsOf(Carbon::parse('2026-05-26 23:30:00', 'Asia/Manila')));

        $invoice->paid_date = '2026-05-26';

        $this->assertFalse($invoice->isOverdueAsOf(Carbon::parse('2026-05-27 00:30:00', 'Asia/Manila')));
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
