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
        Schema::table('teacher_compensations', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('internal_admin_notes');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();

            $table->index(['teacher_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_compensations', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'archived_at']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
        });
    }
};
