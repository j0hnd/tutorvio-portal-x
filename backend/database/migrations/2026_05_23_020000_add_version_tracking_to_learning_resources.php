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
        Schema::table('learning_resources', function (Blueprint $table) {
            $table->unsignedInteger('current_version_number')->nullable()->after('created_by');
        });

        Schema::create('learning_resource_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('previous_file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('preview_metadata')->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['learning_resource_id', 'version_number'], 'learning_resource_versions_unique_version');
            $table->index(['learning_resource_id', 'uploaded_at'], 'learning_resource_versions_resource_uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_resource_versions');

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropColumn('current_version_number');
        });
    }
};
