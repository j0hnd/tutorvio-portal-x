<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lesson_records', function (Blueprint $table) {
            $table->date('homework_due_date')->nullable();
            $table->string('attendance_status')->nullable();
            $table->text('internal_remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_records', function (Blueprint $table) {
            $table->dropColumn([
                'homework_due_date',
                'attendance_status',
                'internal_remarks',
            ]);
        });
    }
};
