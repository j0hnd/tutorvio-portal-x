<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->string('class_type')->default('regular');
            $table->timestampTz('teacher_blocked_until')->nullable();
            $table->index(['teacher_id', 'starts_at', 'teacher_blocked_until'], 'class_schedules_teacher_block_index');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropIndex('class_schedules_teacher_block_index');
            $table->dropColumn(['class_type', 'teacher_blocked_until']);
        });
    }
};
