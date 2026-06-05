<?php

namespace App\Reports\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait PaginatesReportQueries
{
    /**
     * @param  Builder<Model>  $query
     * @param  array{page?: int, per_page?: int}  $pagination
     * @param  Closure(Model): array<string, mixed>  $map
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    protected function pageRows(Builder $query, array $pagination, Closure $map): array
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($pagination['per_page'] ?? 50)));

        return [
            'rows' => (clone $query)
                ->forPage($page, $perPage)
                ->get()
                ->map($map)
                ->values()
                ->all(),
            'total' => (clone $query)->count(),
        ];
    }
}
