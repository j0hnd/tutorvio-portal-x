<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_program_stores_structured_details_and_relationships(): void
    {
        $courseType = CourseType::factory()->create(['name' => 'Business English']);
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        $program = CourseProgram::factory()->create([
            'course_type_id' => $courseType->id,
            'title' => 'Business English Accelerator',
            'placement_level' => 'B1-B2',
            'number_of_sessions' => 10,
            'lesson_structure' => [
                'session_length_minutes' => 50,
                'components' => ['warm_up', 'role_play', 'feedback'],
            ],
            'milestones' => [
                ['session' => 1, 'goal' => 'Needs analysis'],
                ['session' => 10, 'goal' => 'Final workplace scenario'],
            ],
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertTrue($program->courseType->is($courseType));
        $this->assertTrue($program->createdBy->is($creator));
        $this->assertTrue($program->updatedBy->is($updater));
        $this->assertSame('B1-B2', $program->placement_level);
        $this->assertSame(10, $program->number_of_sessions);
        $this->assertSame(['warm_up', 'role_play', 'feedback'], $program->lesson_structure['components']);
        $this->assertSame('Final workplace scenario', $program->milestones[1]['goal']);
    }

    public function test_archived_course_types_are_hidden_by_default(): void
    {
        CourseType::factory()->create(['name' => 'Active Type']);
        CourseType::factory()->archived()->create(['name' => 'Archived Type']);

        $this->assertSame(['Active Type'], CourseType::query()->pluck('name')->all());
        $this->assertCount(2, CourseType::withArchived()->get());
        $this->assertSame(['Archived Type'], CourseType::onlyArchived()->pluck('name')->all());
    }

    public function test_archived_course_programs_are_hidden_by_default(): void
    {
        CourseProgram::factory()->create(['title' => 'Active Program']);
        CourseProgram::factory()->archived()->create(['title' => 'Archived Program']);

        $this->assertSame(['Active Program'], CourseProgram::query()->pluck('title')->all());
        $this->assertCount(2, CourseProgram::withArchived()->get());
        $this->assertSame(['Archived Program'], CourseProgram::onlyArchived()->pluck('title')->all());
    }

    public function test_course_catalog_seeder_creates_required_course_types(): void
    {
        $this->seed(CourseCatalogSeeder::class);

        $this->assertDatabaseHas('course_types', ['name' => 'General English', 'slug' => 'general-english']);
        $this->assertDatabaseHas('course_types', ['name' => 'Business English', 'slug' => 'business-english']);
        $this->assertDatabaseHas('course_types', ['name' => 'Maturita Preparation', 'slug' => 'maturita-preparation']);
        $this->assertDatabaseHas('course_types', ['name' => 'Sunshine Restart Program', 'slug' => 'sunshine-restart-program']);
        $this->assertDatabaseHas('course_types', ['name' => 'English for Work Confidence', 'slug' => 'english-for-work-confidence']);
        $this->assertDatabaseHas('course_types', ['name' => 'Interview Preparation', 'slug' => 'interview-preparation']);
        $this->assertDatabaseHas('course_types', ['name' => 'Travel English', 'slug' => 'travel-english']);
        $this->assertSame(7, CourseType::query()->count());
        $this->assertSame(7, CourseProgram::query()->count());
    }
}
