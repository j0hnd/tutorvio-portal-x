<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesFullTextSearch
{
    /**
     * Apply MariaDB/MySQL full-text search, with a lightweight test fallback for non-full-text drivers.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>|null  $fallbackColumns
     */
    protected function applyFullTextSearch(Builder $query, array $columns, ?string $term, ?array $fallbackColumns = null): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        if (in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $booleanQuery = $this->booleanFullTextQuery($term);

            if ($booleanQuery !== '') {
                return $query->whereFullText($columns, $booleanQuery, ['mode' => 'boolean']);
            }
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($fallbackColumns, $columns, $like): void {
            foreach ($fallbackColumns ?? $columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', $like);
            }
        });
    }

    private function booleanFullTextQuery(string $term): string
    {
        preg_match_all('/[[:alnum:]]{2,}/u', mb_strtolower($term), $matches);

        return collect($matches[0] ?? [])
            ->unique()
            ->take(12)
            ->map(fn (string $token): string => '+'.$token.'*')
            ->implode(' ');
    }
}
