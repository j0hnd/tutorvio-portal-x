<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique()->after('id');
        });

        DB::table('class_schedules')
            ->select('id')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('class_schedules')
                        ->where('id', $row->id)
                        ->whereNull('public_id')
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
