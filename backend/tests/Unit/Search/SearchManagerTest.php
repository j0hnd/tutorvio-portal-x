<?php

namespace Tests\Unit\Search;

use App\Contracts\Search\SearchEngine;
use App\Contracts\Search\SearchService;
use App\Models\Homework;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class SearchManagerTest extends TestCase
{
    public function test_search_service_contract_resolves_from_container(): void
    {
        $this->assertInstanceOf(SearchService::class, $this->app->make(SearchService::class));
    }

    public function test_unavailable_advanced_search_engine_falls_back_to_mariadb_search(): void
    {
        config([
            'search.driver' => 'advanced',
            'search.drivers.advanced' => UnavailableAdvancedSearchEngine::class,
        ]);

        $query = $this->app->make(SearchService::class)
            ->homework(Homework::query(), 'essay');

        $this->assertStringContainsString('homeworks', $query->toSql());
        $this->assertStringContainsString('title', $query->toSql());
    }
}

class UnavailableAdvancedSearchEngine implements SearchEngine
{
    public function available(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'advanced';
    }

    public function students(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function teachers(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function lessons(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function lessonNotes(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function homework(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function resources(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function announcements(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function issues(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    public function auditLogs(Builder $query, ?string $term): Builder
    {
        return $this->unexpected($query);
    }

    private function unexpected(Builder $query): Builder
    {
        return $query->whereRaw('0 = 1');
    }
}
