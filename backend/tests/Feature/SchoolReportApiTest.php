<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Lesson;
use App\Models\User;
use App\Reports\SchoolReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchoolReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private CourseProgram $courseProgram;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');

        $this->courseProgram = CourseProgram::factory()->create();
    }

    public function test_admin_can_request_school_reports_with_normalized_optional_filters(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/school?date_from=2026-06-01T09:30:00%2B08:00&date_to=2026-06-30&teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_id='.$this->courseProgram->id.'&status=completed')
            ->assertOk()
            ->assertJsonPath('data.filters.date_from', '2026-06-01')
            ->assertJsonPath('data.filters.date_to', '2026-06-30')
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.student_id', $this->student->id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id)
            ->assertJsonPath('data.filters.status', 'completed');

        $this->getJson('/api/v1/admin/reports/school')
            ->assertOk()
            ->assertJsonPath('data.filters', []);
    }

    public function test_report_apis_return_consistent_response_shape(): void
    {
        Sanctum::actingAs($this->admin);

        foreach ($this->reportEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure([
                    'success',
                    'filters',
                    'summary',
                    'rows',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                        'has_more_pages',
                    ],
                    'export' => [
                        'supported',
                        'formats',
                    ],
                ])
                ->assertJsonPath('export.supported', true)
                ->assertJsonPath('export.formats', ['csv', 'xlsx', 'pdf']);
        }
    }

    public function test_school_report_filters_reject_invalid_dates_and_ranges(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/school?date_from=2026-07-01&date_to=2026-06-30')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $this->getJson('/api/v1/admin/reports/school?date_from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from');
    }

    public function test_school_report_route_requires_role_and_permission(): void
    {
        $this->getJson('/api/v1/admin/reports/school')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/school')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/school')->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/admin/reports/school')->assertForbidden();
    }

    public function test_school_report_filters_can_apply_common_query_constraints(): void
    {
        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
        ]);

        $matchingLesson = $this->createLesson();
        $this->createLesson(['start_time' => '2026-07-01 09:00:00', 'end_time' => '2026-07-01 10:00:00']);

        $filters = SchoolReportFilters::fromArray([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id,
            'course_id' => $this->courseProgram->id,
            'status' => Lesson::STATUS_COMPLETED,
        ]);

        $lessons = $filters
            ->applyTo(Lesson::query(), [
                'date_column' => 'start_time',
                'course_relation' => 'student.courseProgramAssignments',
            ])
            ->pluck('id');

        $this->assertSame([$matchingLesson->id], $lessons->all());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLesson(array $overrides = []): Lesson
    {
        return Lesson::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'start_time' => '2026-06-15 09:00:00',
            'end_time' => '2026-06-15 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
            ...$overrides,
        ]);
    }

    /**
     * @return list<string>
     */
    private function reportEndpoints(): array
    {
        return [
            '/api/v1/reports/teacher-load',
            '/api/v1/admin/reports/school',
            '/api/v1/admin/reports/active-students',
            '/api/v1/admin/reports/attendance',
            '/api/v1/admin/reports/lesson-completions',
            '/api/v1/admin/reports/teacher-note-completions',
            '/api/v1/admin/reports/student-progress',
            '/api/v1/admin/reports/missed-classes',
            '/api/v1/admin/reports/package-usage',
            '/api/v1/admin/reports/retention-continuation',
            '/api/v1/admin/reports/trial-enrollments',
        ];
    }
}
