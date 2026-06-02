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
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('resource_type');
            $table->string('storage_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('preview_metadata')->nullable();
            $table->string('course')->nullable();
            $table->string('level')->nullable();
            $table->string('visibility')->default('teacher-only');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('resource_type');
            $table->index('visibility');
            $table->index('course');
            $table->index('level');
            $table->index('created_by');
            $table->index(['visibility', 'resource_type']);
            $table->index(['course', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_resources');
    }
};
