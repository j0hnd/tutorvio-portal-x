<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillPublicIds extends Command
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'lessons',
        'invoices',
        'subscriptions',
        'homeworks',
        'message_threads',
        'learning_resources',
        'course_programs',
        'announcements',
        'notifications',
        'audit_logs',
    ];

    protected $signature = 'tvio:backfill-public-ids {--chunk=500 : Number of rows to process per chunk}';

    protected $description = 'Backfill ULID public IDs for existing public API records.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $summary = [];
        $totalUpdated = 0;

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'public_id')) {
                $summary[] = [$table, 'n/a', 0, 'n/a', 'skipped'];

                continue;
            }

            $pending = DB::table($table)->whereNull('public_id')->count();
            $updated = $this->backfillTable($table, $chunkSize);
            $remaining = DB::table($table)->whereNull('public_id')->count();
            $totalUpdated += $updated;

            $summary[] = [$table, $pending, $updated, $remaining, $remaining === 0 ? 'complete' : 'partial'];
        }

        $this->table(['Table', 'Pending', 'Updated', 'Remaining', 'Status'], $summary);
        $this->info("Backfilled {$totalUpdated} public ID(s).");

        return self::SUCCESS;
    }

    private function backfillTable(string $table, int $chunkSize): int
    {
        $updated = 0;

        DB::table($table)
            ->select('id')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($table, &$updated): void {
                foreach ($rows as $row) {
                    $updated += DB::table($table)
                        ->where('id', $row->id)
                        ->whereNull('public_id')
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        return $updated;
    }
}
