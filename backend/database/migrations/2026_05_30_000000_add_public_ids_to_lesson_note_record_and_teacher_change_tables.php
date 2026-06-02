<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'lesson_notes',
        'lesson_records',
        'teacher_change_requests',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->ulid('public_id')->nullable()->unique()->after('id');
            });

            DB::table($tableName)
                ->select('id')
                ->whereNull('public_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->whereNull('public_id')
                            ->update(['public_id' => (string) Str::ulid()]);
                    }
                });

            Schema::table($tableName, function (Blueprint $table) {
                $table->ulid('public_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};
