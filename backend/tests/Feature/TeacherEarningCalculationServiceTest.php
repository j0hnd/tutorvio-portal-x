<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\CourseType;
use App\Models\LessonRecord;
use App\Models\Subscription;
use App\Models\TeacherCompensation;
use App\Models\TeacherCompensationRateRule;
use App\Models\TeacherEarning;
use App\Models\User;
use App\Services\TeacherEarningCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherEarningCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_per_lesson_earning_for_completed_paid_lesson_record(): void
    {
        [$teacher, $student] = $this->users();
        $lessonRecord = $this->eligibleLessonRecord($teacher, $student);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 32.50,
            'currency' => 'USD',
            'effective_start_date' => '2026-05-01',
        ]);

        $earning = app(TeacherEarningCalculationService::class)->calculateForLessonRecord($lessonRecord);

        $this->assertNotNull($earning);
        $this->assertSame($teacher->id, $earning->teacher_id);
        $this->assertSame($lessonRecord->id, $earning->lesson_record_id);
        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_LESSON, $earning->pay_model);
        $this->assertSame('32.50', $earning->rate_used);
        $this->assertSame('1.00', $earning->quantity);
        $this->assertSame('32.50', $earning->amount);
        $this->assertSame('USD', $earning->currency);
        $this->assertSame(TeacherEarning::STATUS_PENDING, $earning->status);
    }

    public function test_calculates_per_hour_earning_using_lesson_duration(): void
    {
        [$teacher, $student] = $this->users();
        $lessonRecord = $this->eligibleLessonRecord($teacher, $student, [
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'default_pay_rate' => 40,
            'effective_start_date' => '2026-05-01',
        ]);

        $earning = app(TeacherEarningCalculationService::class)->calculateForLessonRecord($lessonRecord);

        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_HOUR, $earning?->pay_model);
        $this->assertSame('1.50', $earning?->quantity);
        $this->assertSame('60.00', $earning?->amount);
    }

    public function test_calculates_per_student_earning(): void
    {
        [$teacher, $student] = $this->users();
        $lessonRecord = $this->eligibleLessonRecord($teacher, $student);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_STUDENT,
            'default_pay_rate' => 18,
            'effective_start_date' => '2026-05-01',
        ]);

        $earning = app(TeacherEarningCalculationService::class)->calculateForLessonRecord($lessonRecord);

        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_STUDENT, $earning?->pay_model);
        $this->assertSame('1.00', $earning?->quantity);
        $this->assertSame('18.00', $earning?->amount);
    }

    public function test_calculates_per_course_earning_when_student_has_active_course_assignment(): void
    {
        [$teacher, $student] = $this->users();
        $courseType = CourseType::factory()->create();
        $courseProgram = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $courseProgram->id,
            'student_id' => $student->id,
            'assigned_at' => '2026-05-01 08:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
        $lessonRecord = $this->eligibleLessonRecord($teacher, $student);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_COURSE,
            'default_pay_rate' => 150,
            'effective_start_date' => '2026-05-01',
        ]);

        $earning = app(TeacherEarningCalculationService::class)->calculateForLessonRecord($lessonRecord);

        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_COURSE, $earning?->pay_model);
        $this->assertSame('1.00', $earning?->quantity);
        $this->assertSame('150.00', $earning?->amount);
        $this->assertSame($courseProgram->id, $earning?->calculation_metadata['course_program_id']);
        $this->assertSame($courseType->id, $earning?->calculation_metadata['course_type_id']);
    }

    public function test_applies_variable_rate_rule_overrides(): void
    {
        [$teacher, $student] = $this->users();
        $courseType = CourseType::factory()->create();
        $courseProgram = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $courseProgram->id,
            'student_id' => $student->id,
            'assigned_at' => '2026-05-01 08:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
        $lessonRecord = $this->eligibleLessonRecord($teacher, $student, [
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'meeting_metadata' => [
                'compensation' => [
                    'experience_level' => 'senior',
                    'contract_agreement' => 'premium',
                ],
            ],
        ]);
        $compensation = TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 20,
            'currency' => 'USD',
            'effective_start_date' => '2026-05-01',
        ]);
        $rule = TeacherCompensationRateRule::factory()->create([
            'teacher_compensation_id' => $compensation->id,
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'experience_level' => 'senior',
            'contract_agreement' => 'premium',
            'course_type_id' => $courseType->id,
            'course_program_id' => $courseProgram->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'pay_rate' => 75,
            'currency' => 'EUR',
            'priority' => 50,
        ]);

        $earning = app(TeacherEarningCalculationService::class)->calculateForLessonRecord($lessonRecord);

        $this->assertSame(TeacherCompensation::PAY_MODEL_PER_HOUR, $earning?->pay_model);
        $this->assertSame('75.00', $earning?->rate_used);
        $this->assertSame('1.00', $earning?->quantity);
        $this->assertSame('75.00', $earning?->amount);
        $this->assertSame('EUR', $earning?->currency);
        $this->assertSame($rule->id, $earning?->calculation_metadata['teacher_compensation_rate_rule_id']);
    }

    public function test_skips_ineligible_records_and_prevents_duplicate_earnings(): void
    {
        [$teacher, $student] = $this->users();
        $eligible = $this->eligibleLessonRecord($teacher, $student);
        $cancelled = $this->eligibleLessonRecord($teacher, $student, [
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
            'is_completed' => false,
        ]);
        $unpaidSubscription = Subscription::factory()->unpaid()->create(['user_id' => $student->id]);
        $unpaid = $this->eligibleLessonRecord($teacher, $student, [
            'lesson_balance_consumed_subscription_id' => $unpaidSubscription->id,
        ]);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 30,
            'effective_start_date' => '2026-05-01',
        ]);
        $service = app(TeacherEarningCalculationService::class);

        $first = $service->calculateForLessonRecord($eligible);
        $second = $service->calculateForLessonRecord($eligible);

        $this->assertTrue($first?->is($second));
        $this->assertNull($service->calculateForLessonRecord($cancelled));
        $this->assertNull($service->calculateForLessonRecord($unpaid));
        $this->assertSame(1, TeacherEarning::count());
    }

    public function test_calculates_earnings_for_teacher_records_in_date_range(): void
    {
        [$teacher, $student] = $this->users();
        [$otherTeacher] = $this->users();
        $included = $this->eligibleLessonRecord($teacher, $student, ['scheduled_date' => '2026-05-27']);
        $this->eligibleLessonRecord($teacher, $student, ['scheduled_date' => '2026-05-01']);
        $this->eligibleLessonRecord($otherTeacher, $student, ['scheduled_date' => '2026-05-27']);
        TeacherCompensation::factory()->create([
            'teacher_id' => $teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 30,
            'effective_start_date' => '2026-05-01',
        ]);
        TeacherCompensation::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'default_pay_rate' => 30,
            'effective_start_date' => '2026-05-01',
        ]);

        $earnings = app(TeacherEarningCalculationService::class)
            ->calculateForTeacher($teacher, '2026-05-20', '2026-05-31');

        $this->assertCount(1, $earnings);
        $this->assertSame($included->id, $earnings->first()->lesson_record_id);
        $this->assertSame(1, TeacherEarning::count());
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function users(): array
    {
        $teacher = User::factory()->create();
        $student = User::factory()->create();

        return [$teacher, $student];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function eligibleLessonRecord(User $teacher, User $student, array $attributes = []): LessonRecord
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
        ]);

        return LessonRecord::create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'scheduled_date' => '2026-05-27',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'lesson_type' => LessonRecord::TYPE_PRACTICAL_CONVERSATIONAL_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'is_completed' => true,
            'completed_at' => '2026-05-27 10:00:00',
            'lesson_balance_consumed_subscription_id' => $subscription->id,
            'lesson_balance_consumed_at' => '2026-05-27 10:01:00',
            ...$attributes,
        ]);
    }
}
