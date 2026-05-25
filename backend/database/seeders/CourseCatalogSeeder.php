<?php

namespace Database\Seeders;

use App\Models\CourseProgram;
use App\Models\CourseType;
use Illuminate\Database\Seeder;

class CourseCatalogSeeder extends Seeder
{
    /**
     * Seed the course types and starter program catalog.
     */
    public function run(): void
    {
        foreach ($this->courseTypes() as $index => $courseTypeData) {
            $courseType = CourseType::withArchived()->updateOrCreate(
                ['slug' => $courseTypeData['slug']],
                [
                    'name' => $courseTypeData['name'],
                    'description' => $courseTypeData['description'],
                    'sort_order' => $index + 1,
                    'is_archived' => false,
                    'archived_at' => null,
                    'archived_by' => null,
                ]
            );

            foreach ($courseTypeData['programs'] as $programData) {
                CourseProgram::withArchived()->updateOrCreate(
                    ['slug' => $programData['slug']],
                    [
                        'course_type_id' => $courseType->id,
                        'title' => $programData['title'],
                        'description' => $programData['description'],
                        'placement_level' => $programData['placement_level'],
                        'number_of_sessions' => $programData['number_of_sessions'],
                        'lesson_structure' => $programData['lesson_structure'],
                        'milestones' => $programData['milestones'],
                        'is_archived' => false,
                        'archived_at' => null,
                        'archived_by' => null,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courseTypes(): array
    {
        return [
            [
                'name' => 'General English',
                'slug' => 'general-english',
                'description' => 'Core English development for everyday communication and steady level progression.',
                'programs' => [
                    $this->program('General English Foundation', 'general-english-foundation', 'Build practical grammar, vocabulary, listening, and speaking confidence.', 'A1-A2', 12),
                ],
            ],
            [
                'name' => 'Business English',
                'slug' => 'business-english',
                'description' => 'Workplace communication for meetings, email, presentations, and professional fluency.',
                'programs' => [
                    $this->program('Business English Accelerator', 'business-english-accelerator', 'Practice high-frequency workplace language and professional communication routines.', 'B1-B2', 10),
                ],
            ],
            [
                'name' => 'Maturita Preparation',
                'slug' => 'maturita-preparation',
                'description' => 'Exam-focused preparation for Maturita speaking, reading, listening, and writing tasks.',
                'programs' => [
                    $this->program('Maturita Exam Preparation', 'maturita-exam-preparation', 'Prepare for exam task types with targeted practice and feedback checkpoints.', 'B1-B2', 16),
                ],
            ],
            [
                'name' => 'Sunshine Restart Program',
                'slug' => 'sunshine-restart-program',
                'description' => 'Supportive restart path for learners returning to English after a long break.',
                'programs' => [
                    $this->program('Sunshine Restart Path', 'sunshine-restart-path', 'Refresh essential language and rebuild study habits at a manageable pace.', 'A1-B1', 8),
                ],
            ],
            [
                'name' => 'English for Work Confidence',
                'slug' => 'english-for-work-confidence',
                'description' => 'Confidence-building English for practical job and workplace situations.',
                'programs' => [
                    $this->program('Work Confidence Builder', 'work-confidence-builder', 'Develop workplace speaking confidence through guided scenarios and feedback.', 'A2-B1', 8),
                ],
            ],
            [
                'name' => 'Interview Preparation',
                'slug' => 'interview-preparation',
                'description' => 'Focused preparation for job interviews, self-presentation, and follow-up communication.',
                'programs' => [
                    $this->program('Interview Readiness Sprint', 'interview-readiness-sprint', 'Prepare answers, practice live interview scenarios, and refine professional vocabulary.', 'B1-C1', 6),
                ],
            ],
            [
                'name' => 'Travel English',
                'slug' => 'travel-english',
                'description' => 'Practical English for travel planning, airports, hotels, restaurants, and problem solving abroad.',
                'programs' => [
                    $this->program('Travel English Essentials', 'travel-english-essentials', 'Practice real travel situations with useful vocabulary and speaking drills.', 'A1-B1', 6),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function program(string $title, string $slug, string $description, string $placementLevel, int $sessions): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'placement_level' => $placementLevel,
            'number_of_sessions' => $sessions,
            'lesson_structure' => [
                'session_length_minutes' => 50,
                'delivery_mode' => 'one_to_one',
                'components' => [
                    'warm_up',
                    'target_language',
                    'guided_practice',
                    'communicative_task',
                    'feedback',
                ],
            ],
            'milestones' => [
                ['session' => 1, 'goal' => 'Placement review and personal learning goals'],
                ['session' => max(2, (int) ceil($sessions / 2)), 'goal' => 'Progress checkpoint and targeted adjustment'],
                ['session' => $sessions, 'goal' => 'Final review and next-step recommendation'],
            ],
        ];
    }
}
