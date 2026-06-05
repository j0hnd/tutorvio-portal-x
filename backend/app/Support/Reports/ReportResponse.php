<?php

namespace App\Support\Reports;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ReportResponse
{
    public const EXPORT_FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * @param  array{summary?: array<string, mixed>, rows?: list<array<string, mixed>>, total?: int, filters?: array<string, mixed>}  $report
     * @param  array{page?: int, per_page?: int}  $pagination
     */
    public static function json(array $report, array $pagination = []): JsonResponse
    {
        $payload = self::payload($report, $pagination);

        return response()->json([
            ...$payload,
            'data' => $payload,
        ]);
    }

    /**
     * @param  array{summary?: array<string, mixed>, rows?: list<array<string, mixed>>, total?: int, filters?: array<string, mixed>}  $report
     * @param  array{page?: int, per_page?: int}  $pagination
     * @return array<string, mixed>
     */
    public static function payload(array $report, array $pagination = []): array
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($pagination['per_page'] ?? 50)));
        $rows = collect($report['rows'] ?? [])->values();
        $total = array_key_exists('total', $report) ? max(0, (int) $report['total']) : $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $pageRows = array_key_exists('total', $report) ? $rows : $rows->forPage($page, $perPage)->values();

        return [
            'success' => true,
            'filters' => $report['filters'] ?? [],
            'summary' => $report['summary'] ?? [],
            'rows' => $pageRows->all(),
            'pagination' => self::pagination($pageRows, $page, $perPage, $total, $lastPage),
            'export' => [
                'supported' => true,
                'formats' => self::EXPORT_FORMATS,
                'requires_explicit_request' => true,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|bool|null>
     */
    private static function pagination(Collection $rows, int $page, int $perPage, int $total, int $lastPage): array
    {
        $count = $rows->count();
        $from = $count === 0 ? null : (($page - 1) * $perPage) + 1;

        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $from === null ? null : $from + $count - 1,
            'has_more_pages' => $page < $lastPage,
        ];
    }
}
