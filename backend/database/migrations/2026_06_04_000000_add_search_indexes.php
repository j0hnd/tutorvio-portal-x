<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'id'], 'users_status_created_id_idx');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->index(['assigned_teacher_id', 'start_date'], 'student_profiles_teacher_start_idx');
            $table->index(['course', 'current_level'], 'student_profiles_course_level_idx');
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->index(['internal_status', 'user_id'], 'teacher_profiles_status_user_idx');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['student_id', 'start_time'], 'lessons_student_start_idx');
            $table->index(['teacher_id', 'start_time'], 'lessons_teacher_start_idx');
            $table->index(['status', 'start_time'], 'lessons_status_start_idx');
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->index(['status', 'due_date', 'created_at'], 'homeworks_status_due_created_idx');
            $table->index(['student_id', 'due_date', 'status'], 'homeworks_student_due_status_idx');
            $table->index(['teacher_id', 'due_date', 'status'], 'homeworks_teacher_due_status_idx');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->index(['visibility', 'created_at', 'id'], 'resources_visibility_created_id_idx');
            $table->index(['resource_type', 'created_at', 'id'], 'resources_type_created_id_idx');
            $table->index(['course', 'level', 'visibility'], 'resources_course_level_visibility_idx');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['status', 'is_archived', 'created_at'], 'announcements_status_archived_created_idx');
            $table->index(['status', 'is_archived', 'published_at'], 'announcements_status_archived_published_idx');
        });

        Schema::table('issue_reports', function (Blueprint $table) {
            $table->index(['status', 'priority', 'created_at'], 'issues_status_priority_created_idx');
            $table->index(['issue_type', 'status', 'created_at'], 'issues_type_status_created_idx');
            $table->index(['reporter_id', 'created_at'], 'issues_reporter_created_idx');
            $table->index(['assigned_to_id', 'status', 'created_at'], 'issues_assignee_status_created_idx');
            $table->index(['related_student_id', 'created_at'], 'issues_student_created_idx');
            $table->index(['related_teacher_id', 'created_at'], 'issues_teacher_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['actor_user_id', 'created_at'], 'audit_actor_created_idx');
            $table->index(['action_type', 'created_at'], 'audit_action_created_idx');
            $table->index(['module', 'created_at'], 'audit_module_created_idx');
            $table->index(['target_entity_type', 'target_entity_id', 'created_at'], 'audit_target_created_idx');
        });

        if ($this->supportsFullTextIndexes()) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->longText('metadata_search')->storedAs('JSON_COMPACT(metadata)');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->fullText(['name', 'email'], 'users_name_email_fulltext');
            });

            Schema::table('learning_resources', function (Blueprint $table) {
                $table->fullText(['title', 'description', 'resource_type', 'course', 'level', 'original_filename'], 'resources_search_fulltext');
            });

            Schema::table('announcements', function (Blueprint $table) {
                $table->fullText(['title', 'body'], 'announcements_title_body_fulltext');
            });

            Schema::table('issue_reports', function (Blueprint $table) {
                $table->fullText(['title', 'description', 'resolution_notes'], 'issues_text_fulltext');
            });

            Schema::table('audit_logs', function (Blueprint $table) {
                $table->fullText(['action_type', 'module', 'target_entity_type', 'metadata_search', 'ip_address', 'user_agent'], 'audit_logs_text_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->supportsFullTextIndexes()) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropFullText('audit_logs_text_fulltext');
                $table->dropColumn('metadata_search');
            });

            Schema::table('issue_reports', function (Blueprint $table) {
                $table->dropFullText('issues_text_fulltext');
            });

            Schema::table('announcements', function (Blueprint $table) {
                $table->dropFullText('announcements_title_body_fulltext');
            });

            Schema::table('learning_resources', function (Blueprint $table) {
                $table->dropFullText('resources_search_fulltext');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropFullText('users_name_email_fulltext');
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_target_created_idx');
            $table->dropIndex('audit_module_created_idx');
            $table->dropIndex('audit_action_created_idx');
            $table->dropIndex('audit_actor_created_idx');
        });

        Schema::table('issue_reports', function (Blueprint $table) {
            $table->dropIndex('issues_teacher_created_idx');
            $table->dropIndex('issues_student_created_idx');
            $table->dropIndex('issues_assignee_status_created_idx');
            $table->dropIndex('issues_reporter_created_idx');
            $table->dropIndex('issues_type_status_created_idx');
            $table->dropIndex('issues_status_priority_created_idx');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_status_archived_published_idx');
            $table->dropIndex('announcements_status_archived_created_idx');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropIndex('resources_course_level_visibility_idx');
            $table->dropIndex('resources_type_created_id_idx');
            $table->dropIndex('resources_visibility_created_id_idx');
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropIndex('homeworks_teacher_due_status_idx');
            $table->dropIndex('homeworks_student_due_status_idx');
            $table->dropIndex('homeworks_status_due_created_idx');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_status_start_idx');
            $table->dropIndex('lessons_teacher_start_idx');
            $table->dropIndex('lessons_student_start_idx');
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropIndex('teacher_profiles_status_user_idx');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex('student_profiles_course_level_idx');
            $table->dropIndex('student_profiles_teacher_start_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_created_id_idx');
        });
    }

    private function supportsFullTextIndexes(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
