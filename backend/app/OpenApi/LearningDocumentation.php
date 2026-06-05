<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Learning',
    description: 'Student learning path, course program, academic record, homework, and progress APIs. All endpoints require Sanctum bearer authentication. Access rules: admins have full access; staff users need the documented permission; teachers are scoped to students assigned to them; students are scoped to their own active course programs, academic records, homework, and progress. Student-facing responses omit internal audit and administrative fields unless the authenticated user can view those fields.'
)]
#[OA\Schema(
    schema: 'LearningAcademicRecordStatus',
    description: 'Academic record lifecycle status. Archived records are hidden from student and teacher direct access; admins and staff with `academic_records.view` can inspect them.',
    type: 'string',
    enum: ['active', 'archived', 'void'],
    example: 'active'
)]
#[OA\Schema(
    schema: 'LearningAcademicRecordType',
    type: 'string',
    enum: ['progress', 'attendance', 'assessment', 'note', 'certificate', 'placement', 'homework'],
    example: 'progress'
)]
#[OA\Schema(
    schema: 'LearningHomeworkStatus',
    description: '`assigned` is newly created teacher work; `in_progress` and `completed` are student/admin progress states; `reviewed` is set by teacher/admin review; `overdue` may be stored or derived in reports when the due date has passed without completion/review.',
    type: 'string',
    enum: ['assigned', 'in_progress', 'completed', 'reviewed', 'overdue'],
    example: 'assigned'
)]
#[OA\Schema(
    schema: 'LearningProgressStatus',
    description: 'Progress and goal status values used by student progress records and reports.',
    type: 'string',
    enum: ['not_started', 'in_progress', 'completed', 'needs_support'],
    example: 'in_progress'
)]
#[OA\Schema(
    schema: 'LearningSkillArea',
    description: 'Skill areas accepted by progress records, progress summaries, and timeline filters.',
    type: 'string',
    enum: ['speaking', 'listening', 'vocabulary', 'grammar', 'pronunciation', 'confidence', 'fluency', 'workplace_communication'],
    example: 'speaking'
)]
#[OA\Schema(
    schema: 'LearningRating',
    type: 'string',
    enum: ['needs_support', 'developing', 'confident', 'strong'],
    example: 'confident'
)]
#[OA\Schema(
    schema: 'LearningLevelMovement',
    type: 'string',
    enum: ['moved_up', 'maintained', 'moved_down', 'needs_review'],
    example: 'moved_up'
)]
#[OA\Schema(
    schema: 'LearningUserSummary',
    allOf: [new OA\Schema(ref: '#/components/schemas/UserSummary')]
)]
#[OA\Schema(
    schema: 'LearningCourseProgram',
    description: 'Course program returned to clients. Student and teacher responses include student-facing learning path fields. Admin and permitted staff responses also include archive, creator, updater, and pivot attachment fields.',
    required: ['id', 'title', 'name', 'slug', 'number_of_sessions'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'course_type_id', nullable: true, type: 'string', example: 'ctp_01J0ENGLISH00000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Business English Foundations'),
        new OA\Property(property: 'name', description: 'Alias of `title` for frontend compatibility.', type: 'string', example: 'Business English Foundations'),
        new OA\Property(property: 'slug', type: 'string', example: 'business-english-foundations'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'A 12-session path for meetings, presentations, and workplace fluency.'),
        new OA\Property(property: 'placement_level', nullable: true, type: 'string', example: 'B1'),
        new OA\Property(property: 'number_of_sessions', type: 'integer', example: 12),
        new OA\Property(
            property: 'lesson_structure',
            description: 'Student-facing session plan. Keep internal planning notes out of this field.',
            type: 'array',
            items: new OA\Items(type: 'object'),
            example: [
                ['session' => 1, 'topic' => 'Workplace introductions', 'objective' => 'Introduce role and responsibilities clearly.'],
                ['session' => 4, 'topic' => 'Meeting participation', 'objective' => 'Ask clarifying questions and summarize decisions.'],
            ]
        ),
        new OA\Property(
            property: 'milestones',
            description: 'Student-facing learning goals or milestones when implemented on the course program.',
            type: 'array',
            items: new OA\Items(type: 'object'),
            example: [
                ['session' => 4, 'goal' => 'Complete first progress checkpoint'],
                ['session' => 12, 'goal' => 'Deliver a short workplace presentation'],
            ]
        ),
        new OA\Property(property: 'learning_resources', type: 'array', items: new OA\Items(type: 'object'), example: []),
        new OA\Property(property: 'is_archived', description: 'Internal/admin field. Returned only to admins and staff with `course_programs.view`.', type: 'boolean', example: false),
        new OA\Property(property: 'archived_at', description: 'Internal/admin field.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'created_by', description: 'Internal/admin field with numeric actor ID.', nullable: true, type: 'integer', example: 5),
        new OA\Property(property: 'updated_by', description: 'Internal/admin field with numeric actor ID.', nullable: true, type: 'integer', example: 5),
        new OA\Property(property: 'created_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningCourseProgramRequest',
    required: ['course_type_id', 'title', 'number_of_sessions', 'lesson_structure'],
    properties: [
        new OA\Property(property: 'course_type_id', description: 'Course type public ID.', type: 'string', example: 'ctp_01J0ENGLISH00000000001'),
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Business English Foundations'),
        new OA\Property(property: 'name', description: 'Optional alias accepted when `title` is omitted.', type: 'string', maxLength: 255, example: 'Business English Foundations'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'A 12-session path for workplace fluency.'),
        new OA\Property(property: 'placement_level', nullable: true, type: 'string', maxLength: 255, example: 'B1'),
        new OA\Property(property: 'number_of_sessions', type: 'integer', minimum: 1, maximum: 65535, example: 12),
        new OA\Property(property: 'lesson_structure', type: 'array', items: new OA\Items(type: 'object'), example: [['session' => 1, 'topic' => 'Introductions', 'objective' => 'Present role and responsibilities.']]),
        new OA\Property(property: 'milestones', nullable: true, type: 'array', items: new OA\Items(type: 'object'), example: [['session' => 4, 'goal' => 'Progress checkpoint']]),
        new OA\Property(property: 'learning_resource_ids', description: 'Learning resource public IDs visible to the actor.', type: 'array', items: new OA\Items(type: 'string'), example: ['lrn_01J0RESOURCE00000000001', 'lrn_01J0RESOURCE00000000002']),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningCourseAssignment',
    description: 'Student-course assignment. Admin/staff responses include `assigned_by`, `assigned_by_user`, `notes`, and timestamps; student and teacher responses only receive student-facing assignment fields.',
    properties: [
        new OA\Property(property: 'course_program_id', type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'course_program', ref: '#/components/schemas/LearningCourseProgram'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'student', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'assigned_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'removed'], example: 'active'),
        new OA\Property(property: 'start_date', nullable: true, type: 'string', format: 'date', example: '2026-06-10'),
        new OA\Property(property: 'assigned_by', description: 'Internal/admin numeric actor ID.', nullable: true, type: 'integer', example: 5),
        new OA\Property(property: 'assigned_by_user', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'notes', description: 'Internal/admin notes. Hidden from student and teacher responses.', nullable: true, type: 'string', example: 'Assigned after placement review.'),
        new OA\Property(property: 'created_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningAssignStudentsRequest',
    required: ['student_ids'],
    properties: [
        new OA\Property(property: 'student_ids', description: 'Student user public IDs. Each user must have the student role and no active assignment for this course program.', type: 'array', items: new OA\Items(type: 'string'), example: ['usr_01J0STUDENT000000000000001', 'usr_01J0STUDENT000000000000002']),
        new OA\Property(property: 'start_date', nullable: true, type: 'string', format: 'date', example: '2026-06-10'),
        new OA\Property(property: 'notes', description: 'Internal/admin note hidden from student-facing responses.', nullable: true, type: 'string', maxLength: 5000, example: 'Assigned after placement review.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningHomework',
    description: 'Homework returned to clients. Student-facing fields include title, instructions, due date, status, feedback, links, documents, lesson, student, and teacher summaries. `created_at` and `updated_at` are admin/internal fields.',
    required: ['id', 'title', 'status', 'links', 'attachment_links'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'hwk_01J0HOMEWORK00000000001'),
        new OA\Property(property: 'lesson_id', type: 'string', example: 'les_01J0LESSON000000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Prepare five meeting updates'),
        new OA\Property(property: 'instructions', nullable: true, type: 'string', example: 'Write five short updates using past-tense project language.'),
        new OA\Property(property: 'due_date', nullable: true, type: 'string', format: 'date', example: '2026-06-07'),
        new OA\Property(property: 'status', ref: '#/components/schemas/LearningHomeworkStatus'),
        new OA\Property(property: 'teacher_feedback', nullable: true, type: 'string', example: 'Good structure. Review article usage before the next lesson.'),
        new OA\Property(property: 'completed_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-06T12:00:00Z'),
        new OA\Property(property: 'reviewed_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-06T13:00:00Z'),
        new OA\Property(property: 'links', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), example: ['https://example.com/homework-doc']),
        new OA\Property(property: 'attachment_links', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), example: ['https://example.com/homework-doc']),
        new OA\Property(property: 'documents', type: 'array', items: new OA\Items(type: 'object'), example: []),
        new OA\Property(property: 'student', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'created_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningHomeworkRequest',
    required: ['lesson_id', 'student_id', 'title'],
    properties: [
        new OA\Property(property: 'lesson_id', description: 'Lesson public ID. Teachers can only assign homework for their own lessons.', type: 'string', example: 'les_01J0LESSON000000000000001'),
        new OA\Property(property: 'student_id', description: 'Student user public ID. Must match the lesson student.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Prepare five meeting updates'),
        new OA\Property(property: 'instructions', nullable: true, type: 'string', example: 'Write five short updates using past-tense project language.'),
        new OA\Property(property: 'due_date', nullable: true, type: 'string', format: 'date', example: '2026-06-07'),
        new OA\Property(property: 'documents', description: 'Learning resource public IDs visible to the actor.', type: 'array', items: new OA\Items(type: 'string'), example: ['lrn_01J0RESOURCE00000000001', 'lrn_01J0RESOURCE00000000002']),
        new OA\Property(property: 'links', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), example: ['https://example.com/homework-doc']),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningStudentProgressRecord',
    description: 'Progress record. Student-facing fields include skill summaries, ratings, goals, milestones, and teacher comments. Admin/staff responses include creator/updater numeric IDs, actor summaries, and timestamps.',
    required: ['id', 'skill_area', 'progress_summary_by_skill', 'milestone_achievements', 'goals_completed', 'goals_in_progress'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'spr_01J0PROGRESS0000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'skill_area', ref: '#/components/schemas/LearningSkillArea'),
        new OA\Property(property: 'progress_summary_by_skill', type: 'object', example: ['speaking' => 'Uses longer answers with fewer pauses.', 'grammar' => 'Needs support with articles.']),
        new OA\Property(property: 'speaking_confidence_rating', ref: '#/components/schemas/LearningRating'),
        new OA\Property(property: 'vocabulary_progress', nullable: true, type: 'string', example: 'Can use common workplace update phrases.'),
        new OA\Property(property: 'grammar_development', nullable: true, type: 'string', example: 'Maintains past tense in short summaries.'),
        new OA\Property(property: 'pronunciation_progress', nullable: true, type: 'string', example: 'Improved final consonant clarity.'),
        new OA\Property(property: 'lesson_completion_count', nullable: true, type: 'integer', example: 8),
        new OA\Property(property: 'teacher_comments', nullable: true, type: 'string', example: 'Ready for longer meeting simulation.'),
        new OA\Property(property: 'milestone_achievements', type: 'array', items: new OA\Items(type: 'string'), example: ['Completed first progress checkpoint']),
        new OA\Property(property: 'level_movement', ref: '#/components/schemas/LearningLevelMovement'),
        new OA\Property(property: 'goals_completed', type: 'array', items: new OA\Items(type: 'string'), example: ['Introduce project status clearly']),
        new OA\Property(property: 'goals_in_progress', type: 'array', items: new OA\Items(type: 'string'), example: ['Ask concise follow-up questions']),
        new OA\Property(property: 'progress_status', ref: '#/components/schemas/LearningProgressStatus'),
        new OA\Property(property: 'goal_status', description: 'Alias of `progress_status` in responses and accepted in requests for compatibility.', ref: '#/components/schemas/LearningProgressStatus'),
        new OA\Property(property: 'recorded_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'student', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'created_by', description: 'Internal/admin numeric actor ID.', type: 'integer', example: 17),
        new OA\Property(property: 'updated_by', description: 'Internal/admin numeric actor ID.', type: 'integer', example: 17),
        new OA\Property(property: 'created_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningStudentProgressRecordRequest',
    required: ['student_id', 'teacher_id', 'skill_area'],
    properties: [
        new OA\Property(property: 'student_id', description: 'Student user public ID.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', description: 'Teacher user public ID. Teachers can only create/update records as themselves for assigned students.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'skill_area', ref: '#/components/schemas/LearningSkillArea'),
        new OA\Property(property: 'progress_summary_by_skill', type: 'object', example: ['speaking' => 'Uses longer answers with fewer pauses.']),
        new OA\Property(property: 'speaking_confidence_rating', ref: '#/components/schemas/LearningRating'),
        new OA\Property(property: 'vocabulary_progress', nullable: true, type: 'string', maxLength: 5000, example: 'Can use common workplace update phrases.'),
        new OA\Property(property: 'grammar_development', nullable: true, type: 'string', maxLength: 5000, example: 'Maintains past tense in short summaries.'),
        new OA\Property(property: 'pronunciation_progress', nullable: true, type: 'string', maxLength: 5000, example: 'Improved final consonant clarity.'),
        new OA\Property(property: 'lesson_completion_count', type: 'integer', minimum: 0, maximum: 10000, example: 8),
        new OA\Property(property: 'teacher_comments', nullable: true, type: 'string', maxLength: 10000, example: 'Ready for longer meeting simulation.'),
        new OA\Property(property: 'milestone_achievements', nullable: true, type: 'array', items: new OA\Items(type: 'string'), example: ['Completed first progress checkpoint']),
        new OA\Property(property: 'level_movement', ref: '#/components/schemas/LearningLevelMovement'),
        new OA\Property(property: 'goals_completed', nullable: true, type: 'array', items: new OA\Items(type: 'string'), example: ['Introduce project status clearly']),
        new OA\Property(property: 'goals_in_progress', nullable: true, type: 'array', items: new OA\Items(type: 'string'), example: ['Ask concise follow-up questions']),
        new OA\Property(property: 'progress_status', ref: '#/components/schemas/LearningProgressStatus'),
        new OA\Property(property: 'goal_status', ref: '#/components/schemas/LearningProgressStatus'),
        new OA\Property(property: 'recorded_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningAcademicRecord',
    description: 'Academic record returned to clients. Student and teacher responses omit internal notes and administrative audit fields. Archived records are only visible to admins and staff with `academic_records.view`.',
    required: ['id', 'record_type', 'title', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'acr_01J0ACADEMIC0000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'lesson_id', nullable: true, type: 'string', example: 'les_01J0LESSON000000000000001'),
        new OA\Property(property: 'class_schedule_id', nullable: true, type: 'string', example: 'cls_01J0CLASS0000000000000001'),
        new OA\Property(property: 'record_type', ref: '#/components/schemas/LearningAcademicRecordType'),
        new OA\Property(property: 'title', type: 'string', example: 'Academic progress summary'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'Monthly progress review.'),
        new OA\Property(property: 'status', ref: '#/components/schemas/LearningAcademicRecordStatus'),
        new OA\Property(property: 'recorded_on', nullable: true, type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'student_level', nullable: true, type: 'string', example: 'B1'),
        new OA\Property(property: 'placement_result', nullable: true, type: 'string', example: 'Placed into intermediate conversation program.'),
        new OA\Property(property: 'course_program_history', type: 'array', items: new OA\Items(type: 'object'), example: [['program' => 'General English', 'status' => 'completed']]),
        new OA\Property(property: 'attendance_summary', type: 'object', example: ['completed' => 12, 'missed' => 1]),
        new OA\Property(property: 'progress_summary', nullable: true, type: 'string', example: 'Improving fluency.'),
        new OA\Property(property: 'teacher_remarks', description: 'Internal/admin field.', nullable: true, type: 'string', example: 'Ready for guided debates.'),
        new OA\Property(property: 'certificates', type: 'array', items: new OA\Items(type: 'object'), example: [['name' => 'B1 Completion', 'issued_on' => '2026-06-15']]),
        new OA\Property(property: 'completion_notes', nullable: true, type: 'string', example: 'Completed first course cycle.'),
        new OA\Property(property: 'internal_notes', description: 'Internal/admin field.', nullable: true, type: 'string', example: 'Internal billing-related note.'),
        new OA\Property(property: 'data', type: 'object', example: ['student_level' => 'B1', 'progress_summary' => 'Improving fluency.']),
        new OA\Property(property: 'student', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'course_program', ref: '#/components/schemas/LearningCourseProgram'),
        new OA\Property(property: 'recorded_by', description: 'Internal/admin field.', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'approved_by', description: 'Internal/admin field.', nullable: true, type: 'string', example: null),
        new OA\Property(property: 'approved_at', description: 'Internal/admin field.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'archived_by', description: 'Internal/admin field.', nullable: true, type: 'string', example: null),
        new OA\Property(property: 'archived_at', description: 'Internal/admin field.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'created_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Internal/admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LearningAcademicRecordRequest',
    required: ['student_id', 'record_type', 'title'],
    properties: [
        new OA\Property(property: 'student_id', description: 'Student user public ID.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', description: 'Teacher user public ID.', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'lesson_id', nullable: true, type: 'string', example: 'les_01J0LESSON000000000000001'),
        new OA\Property(property: 'class_schedule_id', nullable: true, type: 'string', example: 'cls_01J0CLASS0000000000000001'),
        new OA\Property(property: 'record_type', ref: '#/components/schemas/LearningAcademicRecordType'),
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Academic progress summary'),
        new OA\Property(property: 'description', nullable: true, type: 'string', maxLength: 10000, example: 'Monthly progress review.'),
        new OA\Property(property: 'status', ref: '#/components/schemas/LearningAcademicRecordStatus'),
        new OA\Property(property: 'recorded_on', nullable: true, type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'data', nullable: true, type: 'object', example: ['student_level' => 'B1']),
        new OA\Property(property: 'student_level', nullable: true, type: 'string', maxLength: 255, example: 'B1'),
        new OA\Property(property: 'placement_result', nullable: true, type: 'string', maxLength: 5000, example: 'Placed into intermediate conversation program.'),
        new OA\Property(property: 'course_program_history', nullable: true, type: 'array', items: new OA\Items(type: 'object'), example: [['program' => 'General English', 'status' => 'completed']]),
        new OA\Property(property: 'attendance_summary', nullable: true, type: 'object', example: ['completed' => 12, 'missed' => 1]),
        new OA\Property(property: 'progress_summary', nullable: true, type: 'string', maxLength: 10000, example: 'Strong speaking progress.'),
        new OA\Property(property: 'teacher_remarks', nullable: true, type: 'string', maxLength: 10000, example: 'Ready for guided debates.'),
        new OA\Property(property: 'certificates', nullable: true, type: 'array', items: new OA\Items(type: 'object'), example: [['name' => 'B1 Completion', 'issued_on' => '2026-06-15']]),
        new OA\Property(property: 'completion_notes', nullable: true, type: 'string', maxLength: 10000, example: 'Completed first course cycle.'),
        new OA\Property(property: 'internal_notes', nullable: true, type: 'string', maxLength: 10000, example: 'Internal note.'),
        new OA\Property(property: 'approved_by', nullable: true, type: 'string', example: null),
        new OA\Property(property: 'approved_at', nullable: true, type: 'string', format: 'date-time', example: null),
    ],
    type: 'object'
)]
#[OA\Get(
    path: '/academic-records',
    operationId: 'learningAcademicRecordList',
    summary: 'List academic records',
    description: 'Access: admin, student, teacher with `academic_records.view`, or staff with `academic_records.view`. Visibility defaults to active records only. Students see only their own active records when student academic record visibility is enabled; teachers see active records for assigned students; admins/permitted staff can filter by status, including archived.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'course_program_id', in: 'query', schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001'),
        new OA\Parameter(name: 'lesson_id', in: 'query', schema: new OA\Schema(type: 'string'), example: 'les_01J0LESSON000000000000001'),
        new OA\Parameter(name: 'class_schedule_id', in: 'query', schema: new OA\Schema(type: 'string'), example: 'cls_01J0CLASS0000000000000001'),
        new OA\Parameter(name: 'record_type', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningAcademicRecordType'), example: 'progress'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningAcademicRecordStatus'), example: 'active'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated academic records.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'acr_01J0ACADEMIC0000000001', 'record_type' => 'progress', 'title' => 'Academic progress summary', 'status' => 'active', 'recorded_on' => '2026-06-15', 'progress_summary' => 'Improving fluency.']], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/academic-records',
    operationId: 'learningAcademicRecordCreate',
    summary: 'Create academic record',
    description: 'Access: admin or staff with `academic_records.manage`. Linked student, teacher, lesson, and class schedule IDs are validated for matching roles and ownership.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningAcademicRecordRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Academic record created.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningAcademicRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/academic-records/{academicRecord}',
    operationId: 'learningAcademicRecordShow',
    summary: 'Show academic record',
    description: 'Access: admin, staff with `academic_records.view`, the student owner, or the assigned teacher. Archived records return 403 for students and teachers even when they own or are assigned to the record; admins and permitted staff can view archived records directly.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'academicRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'acr_01J0ACADEMIC0000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Academic record detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningAcademicRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/academic-records/{academicRecord}',
    operationId: 'learningAcademicRecordUpdate',
    summary: 'Update academic record',
    description: 'Access: admin or staff with `academic_records.manage`. Send only changed fields. Linked entity IDs are validated against the selected student and teacher.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'academicRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'acr_01J0ACADEMIC0000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningAcademicRecordRequest', example: ['title' => 'Updated academic summary', 'progress_summary' => 'Confident in longer speaking tasks.'])),
    responses: [
        new OA\Response(response: 200, description: 'Academic record updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningAcademicRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/academic-records/{academicRecord}/archive',
    operationId: 'learningAcademicRecordArchive',
    summary: 'Archive academic record',
    description: 'Access: admin or staff with `academic_records.manage`. Sets `status=archived`, records `archived_by` and `archived_at`, and hides the record from student and teacher direct access.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'academicRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'acr_01J0ACADEMIC0000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Academic record archived.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningAcademicRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/course-programs',
    operationId: 'learningCourseProgramList',
    summary: 'List course programs',
    description: 'Access: admin, teacher, student, or staff with `course_programs.view`. Visibility: admins and permitted staff can view all matching programs and may include archived records; teachers see programs assigned to their assigned active students; students see their own active assigned programs. Internal archive/audit fields are returned only to admins and staff with `course_programs.view`.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), example: 'business'),
        new OA\Parameter(name: 'course_type_id', in: 'query', description: 'Course type public ID.', schema: new OA\Schema(type: 'string'), example: 'ctp_01J0ENGLISH00000000001'),
        new OA\Parameter(name: 'placement_level', in: 'query', schema: new OA\Schema(type: 'string'), example: 'B1'),
        new OA\Parameter(name: 'include_archived', in: 'query', description: 'Honored only for admin/permitted staff.', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'only_archived', in: 'query', description: 'Honored only for admin/permitted staff.', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['title', 'placement_level', 'number_of_sessions', 'created_at', 'updated_at']), example: 'title'),
        new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']), example: 'asc'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated course programs.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'crs_01J0BUSINESS000000000001', 'title' => 'Business English Foundations', 'name' => 'Business English Foundations', 'slug' => 'business-english-foundations', 'placement_level' => 'B1', 'number_of_sessions' => 12, 'lesson_structure' => [['session' => 1, 'topic' => 'Introductions']], 'milestones' => [['session' => 4, 'goal' => 'Progress checkpoint']], 'learning_resources' => []]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/course-programs',
    operationId: 'learningCourseProgramCreate',
    summary: 'Create course program',
    description: 'Access: admin or staff with `course_programs.create`. The request uses internal numeric IDs for course types and learning resources; responses use public IDs. `lesson_structure` and `milestones` are student-facing, so keep private curriculum planning notes out of those arrays.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningCourseProgramRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Course program created.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningCourseProgram')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/course-programs/{courseProgram}',
    operationId: 'learningCourseProgramShow',
    summary: 'Show course program',
    description: 'Access follows course program visibility rules. Use the course program public ID in the path.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Course program detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningCourseProgram')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/course-programs/{courseProgram}',
    operationId: 'learningCourseProgramUpdate',
    summary: 'Update course program',
    description: 'Access: admin or staff with `course_programs.update`. Send only changed fields. Updating `title` regenerates the slug when needed.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningCourseProgramRequest', example: ['title' => 'Business English Foundations Plus', 'milestones' => [['session' => 6, 'goal' => 'Deliver a project update']]])),
    responses: [
        new OA\Response(response: 200, description: 'Course program updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningCourseProgram')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/course-programs/{courseProgram}/archive',
    operationId: 'learningCourseProgramArchive',
    summary: 'Archive course program',
    description: 'Access: admin or staff with `course_programs.delete`. Archives rather than hard-deleting; archived programs are hidden from student/teacher listings.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Course program archived.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningCourseProgram')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Post(
    path: '/course-programs/{courseProgram}/students',
    operationId: 'learningCourseProgramAssignStudents',
    summary: 'Assign students to course program',
    description: 'Access: admin or staff with `course_programs.update`. Uses internal numeric student IDs in the request. Duplicate active assignments for the same course program are rejected.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningAssignStudentsRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Students assigned.', content: new OA\JsonContent(type: 'object', example: ['data' => [['course_program_id' => 'crs_01J0BUSINESS000000000001', 'student_id' => 'usr_01J0STUDENT000000000000001', 'assigned_at' => '2026-06-01T09:00:00Z', 'status' => 'active', 'start_date' => '2026-06-10', 'notes' => 'Assigned after placement review.']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/course-programs/{courseProgram}/students',
    operationId: 'learningCourseProgramStudents',
    summary: 'List assigned students for a course program',
    description: 'Access: admin or staff with `course_programs.view`. Teacher and student users do not receive this administrative assignment list.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated active assignments.', content: new OA\JsonContent(type: 'object', example: ['data' => [['course_program_id' => 'crs_01J0BUSINESS000000000001', 'student_id' => 'usr_01J0STUDENT000000000000001', 'status' => 'active', 'start_date' => '2026-06-10']], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Delete(
    path: '/course-programs/{courseProgram}/students/{student}',
    operationId: 'learningCourseProgramRemoveStudent',
    summary: 'Remove student course assignment',
    description: 'Access: admin or staff with `course_programs.update`. Marks the active assignment as `removed`; it does not delete the student or course program.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'courseProgram', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001'),
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 204, description: 'Assignment removed.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/students/{student}/course-programs',
    operationId: 'learningStudentCoursePrograms',
    summary: 'List a student learning path',
    description: 'Access: admin, staff with `course_programs.view`, the student themself, or the teacher assigned to the student. Returns active course assignments with course program details and visible learning resources.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated active course assignments for the student.', content: new OA\JsonContent(type: 'object', example: ['data' => [['course_program_id' => 'crs_01J0BUSINESS000000000001', 'course_program' => ['id' => 'crs_01J0BUSINESS000000000001', 'title' => 'Business English Foundations', 'milestones' => [['session' => 4, 'goal' => 'Progress checkpoint']]], 'student_id' => 'usr_01J0STUDENT000000000000001', 'status' => 'active', 'start_date' => '2026-06-10']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/homeworks',
    operationId: 'learningHomeworkList',
    summary: 'List homework',
    description: 'Access: admin, teacher, student, or staff with `homeworks.view`. Visibility: teachers see homework they assigned; students see their own homework; admins/permitted staff can filter broadly. Student-facing responses hide internal timestamps.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'lesson_id', in: 'query', description: 'Lesson public ID.', schema: new OA\Schema(type: 'string'), example: 'les_01J0LESSON000000000000001'),
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningHomeworkStatus'), example: 'completed'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated homework list.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'hwk_01J0HOMEWORK00000000001', 'title' => 'Prepare five meeting updates', 'due_date' => '2026-06-07', 'status' => 'assigned', 'links' => ['https://example.com/homework-doc']]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/homeworks',
    operationId: 'learningHomeworkCreate',
    summary: 'Assign homework',
    description: 'Access: admin or teacher. Teachers can assign homework only for their own lessons and students assigned to them. Created homework starts as `assigned`.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningHomeworkRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Homework assigned.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningHomework')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/homeworks/{homework}',
    operationId: 'learningHomeworkShow',
    summary: 'Show homework',
    description: 'Access: admin, staff with `homeworks.view`, assigning teacher, or assigned student.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'homework', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'hwk_01J0HOMEWORK00000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Homework detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningHomework')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/homeworks/{homework}/progress',
    operationId: 'learningHomeworkUpdateProgress',
    summary: 'Update homework completion',
    description: 'Access: admin or assigned student. Accepted status values are only `in_progress` and `completed`. Setting `completed` records `completed_at`; moving back to `in_progress` clears it.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'homework', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'hwk_01J0HOMEWORK00000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [new OA\Property(property: 'status', type: 'string', enum: ['in_progress', 'completed'], example: 'completed')], type: 'object', example: ['status' => 'completed'])),
    responses: [
        new OA\Response(response: 200, description: 'Homework progress updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningHomework')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/homeworks/{homework}/review',
    operationId: 'learningHomeworkReview',
    summary: 'Review homework',
    description: 'Access: admin or assigning teacher. Review sets status to `reviewed`, stores optional feedback, and records `reviewed_at`. Teacher feedback is student-facing.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'homework', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'hwk_01J0HOMEWORK00000000001')],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'teacher_feedback', nullable: true, type: 'string', example: 'Good structure. Review article usage before the next lesson.')], type: 'object', example: ['teacher_feedback' => 'Good structure. Review article usage before the next lesson.'])),
    responses: [
        new OA\Response(response: 200, description: 'Homework reviewed.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningHomework')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/student-progress-records',
    operationId: 'learningProgressRecordList',
    summary: 'List student progress records',
    description: 'Access: admin, teacher, student, or staff with `student_progress_records.view`. Visibility: students see their own records; teachers see records for assigned students; admins/permitted staff can view and filter all records. Internal creator/updater fields are returned only to users allowed to view admin fields.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'skill_area', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningSkillArea'), example: 'speaking'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'level_movement', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningLevelMovement'), example: 'moved_up'),
        new OA\Parameter(name: 'progress_status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningProgressStatus'), example: 'in_progress'),
        new OA\Parameter(name: 'goal_status', in: 'query', description: 'Alias for `progress_status`.', schema: new OA\Schema(ref: '#/components/schemas/LearningProgressStatus'), example: 'in_progress'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated student progress records.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'spr_01J0PROGRESS0000000001', 'skill_area' => 'speaking', 'progress_summary_by_skill' => ['speaking' => 'Uses longer answers with fewer pauses.'], 'progress_status' => 'in_progress', 'goals_completed' => ['Introduce project status clearly'], 'goals_in_progress' => ['Ask concise follow-up questions'], 'milestone_achievements' => ['Completed first progress checkpoint']]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/student-progress-records',
    operationId: 'learningProgressRecordCreate',
    summary: 'Create student progress record',
    description: 'Access: admin, teacher, or staff with `student_progress_records.create`. Teachers can create records only for students assigned to them and only with their own teacher ID. Goals and milestones are implemented as string arrays on the progress record.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningStudentProgressRecordRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Progress record created.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningStudentProgressRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/student-progress-records/{studentProgressRecord}',
    operationId: 'learningProgressRecordShow',
    summary: 'Show student progress record',
    description: 'Access: admin, staff with `student_progress_records.view`, the student themself, or the assigned teacher.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'studentProgressRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'spr_01J0PROGRESS0000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Progress record detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningStudentProgressRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/student-progress-records/{studentProgressRecord}',
    operationId: 'learningProgressRecordUpdate',
    summary: 'Update student progress record',
    description: 'Access: admin, staff with `student_progress_records.update`, or assigned teacher. Send only changed fields. `goal_status` is normalized to `progress_status` when supplied without `progress_status`.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'studentProgressRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'spr_01J0PROGRESS0000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LearningStudentProgressRecordRequest', example: ['progress_status' => 'completed', 'goals_completed' => ['Introduce project status clearly'], 'teacher_comments' => 'Goal completed with minimal prompting.'])),
    responses: [
        new OA\Response(response: 200, description: 'Progress record updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/LearningStudentProgressRecord')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Delete(
    path: '/student-progress-records/{studentProgressRecord}',
    operationId: 'learningProgressRecordDelete',
    summary: 'Delete student progress record',
    description: 'Access: admin or staff with `student_progress_records.delete`.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'studentProgressRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'spr_01J0PROGRESS0000000001')],
    responses: [
        new OA\Response(response: 204, description: 'Progress record deleted.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/students/{student}/progress-summary',
    operationId: 'learningStudentProgressSummary',
    summary: 'Student progress summary',
    description: 'Access: admin, staff with `student_progress_records.view`, the student themself, or the assigned teacher. Includes latest skill-area summaries, current/previous levels, implemented goals, milestones, and level movement history.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001')],
    responses: [
        new OA\Response(response: 200, description: 'Student progress summary.', content: new OA\JsonContent(type: 'object', example: ['data' => ['student' => ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Alex Student', 'current_level' => 'B1', 'previous_level' => 'A2'], 'latest_progress_summary_per_skill_area' => ['speaking' => ['skill_area' => 'speaking', 'summary' => 'Uses longer answers with fewer pauses.', 'record_id' => 'spr_01J0PROGRESS0000000001', 'progress_status' => 'in_progress', 'teacher_comments' => 'Ready for longer meeting simulation.']], 'speaking_confidence_rating' => 'confident', 'lesson_completion_count' => 8, 'goals_completed_count' => 1, 'goals_in_progress_count' => 1, 'goals_completed' => ['Introduce project status clearly'], 'goals_in_progress' => ['Ask concise follow-up questions'], 'milestone_achievements' => [['achievement' => 'Completed first progress checkpoint', 'record_id' => 'spr_01J0PROGRESS0000000001']], 'current_level' => 'B1', 'previous_level' => 'A2', 'level_movement_history' => [['record_id' => 'spr_01J0PROGRESS0000000001', 'level_movement' => 'moved_up']]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/students/{student}/progress-timeline',
    operationId: 'learningStudentProgressTimeline',
    summary: 'Student progress timeline',
    description: 'Access follows progress summary rules. Returns chronological progress records shaped for frontend timeline views, including metrics, goal counts, milestones, and teacher comment summaries.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'skill_area', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningSkillArea'), example: 'speaking'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Student progress timeline.', content: new OA\JsonContent(type: 'object', example: ['data' => ['student' => ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Alex Student'], 'records' => [['id' => 'spr_01J0PROGRESS0000000001', 'date' => '2026-06-01', 'skill_area' => 'speaking', 'metrics' => ['speaking_confidence_rating' => 'confident', 'lesson_completion_count' => 8, 'level_movement' => 'moved_up', 'progress_status' => 'in_progress'], 'goals' => ['completed' => ['Introduce project status clearly'], 'in_progress' => ['Ask concise follow-up questions'], 'completed_count' => 1, 'in_progress_count' => 1], 'milestone_achievements' => ['Completed first progress checkpoint'], 'teacher_comment' => ['comment' => 'Ready for longer meeting simulation.']]]], 'meta' => ['count' => 1, 'filters' => ['skill_area' => 'speaking', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/reports/student-progress',
    operationId: 'learningStudentProgressReport',
    summary: 'Student progress report',
    description: 'Access: route is authenticated; report visibility is scoped by role. Admin/permitted staff can report across students; students are restricted to themselves; teachers are restricted to assigned students. Admin route `/admin/reports/student-progress` additionally requires `school_reports.view`.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID when supported by base report filters.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID when supported by base report filters.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningProgressStatus'), example: 'in_progress'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Student progress report.', content: new OA\JsonContent(type: 'object', example: ['data' => ['summary' => ['students_count' => 1, 'progress_records_count' => 2, 'completed_lessons_count' => 8, 'status_counts' => ['in_progress' => 2]], 'rows' => [['student_id' => 'usr_01J0STUDENT000000000000001', 'student_name' => 'Alex Student', 'progress_level' => 'B1', 'previous_level' => 'A2', 'milestone' => 'Completed first progress checkpoint', 'skill_progress' => ['speaking' => ['record_id' => 'spr_01J0PROGRESS00000000001', 'summary' => 'Uses longer answers with fewer pauses.', 'status' => 'in_progress']], 'current_progress_status' => 'in_progress']], 'filters' => ['date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'status' => 'in_progress']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/homeworks/summary',
    operationId: 'learningAdminHomeworkSummary',
    summary: 'Homework completion summary',
    description: 'Access: admin or staff with `homeworks.view` under the admin route group. Summarizes assignment, completion, review, and overdue rates for operations dashboards.',
    security: [['sanctum' => []]],
    tags: ['Learning'],
    parameters: [
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'teacher_id', in: 'query', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'student_id', in: 'query', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/LearningHomeworkStatus'), example: 'completed'),
        new OA\Parameter(name: 'course', in: 'query', schema: new OA\Schema(type: 'string'), example: 'Business English'),
        new OA\Parameter(name: 'level', in: 'query', schema: new OA\Schema(type: 'string'), example: 'B1'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Homework summary.', content: new OA\JsonContent(type: 'object', example: ['data' => ['filters' => ['date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'student_id' => null, 'status' => null, 'course' => 'Business English', 'level' => 'B1'], 'summary' => ['total_assigned' => 20, 'assigned_count' => 5, 'in_progress_count' => 3, 'completed_count' => 7, 'reviewed_count' => 4, 'overdue_count' => 1, 'completion_rate' => 0.55, 'overdue_rate' => 0.05], 'trends' => [['date' => '2026-06-01', 'total_assigned' => 4, 'completed_count' => 2, 'reviewed_count' => 1, 'overdue_count' => 0, 'completion_rate' => 0.75, 'overdue_rate' => 0]]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
class LearningDocumentation {}
