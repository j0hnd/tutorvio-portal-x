<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherCompensationRateRule;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherCompensationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_compensation_stores_effective_default_pay_configuration(): void
    {
        $teacher = User::factory()->create();
        $teacherProfile = TeacherProfile::create([
            'user_id' => $teacher->id,
            'specialization' => 'Business English',
            'internal_status' => 'active',
        ]);

        $compensation = TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 32.50,
            'currency' => 'EUR',
            'effective_start_date' => '2026-06-01',
            'effective_end_date' => null,
            'internal_admin_notes' => 'Payroll-only note.',
        ]);

        $this->assertTrue($compensation->teacher->is($teacher));
        $this->assertTrue($teacher->teacherCompensations->first()->is($compensation));
        $this->assertTrue($teacherProfile->compensations->first()->is($compensation));
        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_LESSON, $compensation->pay_model);
        $this->assertSame('32.50', $compensation->default_pay_rate);
        $this->assertSame('EUR', $compensation->currency);
        $this->assertSame('2026-06-01', $compensation->effective_start_date->toDateString());
        $this->assertNull($compensation->effective_end_date);
        $this->assertSame('Payroll-only note.', $compensation->internal_admin_notes);
        $this->assertNotNull($compensation->created_at);
        $this->assertNotNull($compensation->updated_at);
    }

    public function test_teacher_compensation_supports_variable_rate_rules(): void
    {
        $courseType = CourseType::factory()->create(['name' => 'Business English']);
        $courseProgram = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);
        $compensation = TeacherCompensation::factory()->create([
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'default_pay_rate' => 25.00,
            'currency' => 'USD',
        ]);

        $rule = TeacherCompensationRateRule::factory()->create([
            'teacher_compensation_id' => $compensation->id,
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'experience_level' => 'senior',
            'contract_agreement' => 'premium',
            'course_type_id' => $courseType->id,
            'course_program_id' => $courseProgram->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_COURSE,
            'pay_rate' => 150.00,
            'currency' => 'EUR',
            'priority' => 20,
            'is_active' => true,
            'internal_admin_notes' => 'Course-specific override.',
        ]);

        $this->assertTrue($compensation->rateRules->first()->is($rule));
        $this->assertTrue($rule->teacherCompensation->is($compensation));
        $this->assertTrue($rule->courseType->is($courseType));
        $this->assertTrue($rule->courseProgram->is($courseProgram));
        $this->assertSame(LessonRecord::TYPE_BUSINESS_ENGLISH, $rule->lesson_type);
        $this->assertSame('senior', $rule->experience_level);
        $this->assertSame('premium', $rule->contract_agreement);
        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_COURSE, $rule->pay_model);
        $this->assertSame('150.00', $rule->pay_rate);
        $this->assertSame('EUR', $rule->currency);
        $this->assertSame(20, $rule->priority);
        $this->assertTrue($rule->is_active);
        $this->assertNotNull($rule->created_at);
        $this->assertNotNull($rule->updated_at);
    }

    public function test_effective_on_scope_returns_current_compensation_records(): void
    {
        TeacherCompensation::factory()->create([
            'effective_start_date' => '2026-01-01',
            'effective_end_date' => '2026-03-31',
        ]);
        $active = TeacherCompensation::factory()->create([
            'effective_start_date' => '2026-04-01',
            'effective_end_date' => null,
        ]);

        $this->assertSame(
            [$active->id],
            TeacherCompensation::query()->effectiveOn('2026-05-27')->pluck('id')->all()
        );
    }

    public function test_payroll_permissions_are_not_seeded_to_teachers_or_students(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create()->assignRole('admin');
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');

        $this->assertTrue($admin->can('teacher_compensations.view'));
        $this->assertTrue($admin->can('teacher_compensations.manage'));
        $this->assertFalse($teacher->can('teacher_compensations.view'));
        $this->assertFalse($teacher->can('teacher_compensations.manage'));
        $this->assertFalse($student->can('teacher_compensations.view'));
        $this->assertFalse($student->can('teacher_compensations.manage'));
    }
}
