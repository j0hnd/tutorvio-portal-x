<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchEngine;
use App\Contracts\Search\SearchService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class SearchManager implements SearchService
{
    public function __construct(
        private readonly Container $container,
        private readonly MariaDbSearchService $fallback,
    ) {}

    public function students(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function teachers(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function lessons(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function lessonNotes(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function homework(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function resources(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function announcements(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function issues(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    public function auditLogs(Builder $query, ?string $term): Builder
    {
        return $this->apply(__FUNCTION__, $query, $term);
    }

    private function apply(string $method, Builder $query, ?string $term): Builder
    {
        $engine = $this->engine();

        if ($engine === null || ! $engine->available()) {
            return $this->fallback->{$method}($query, $term);
        }

        try {
            return $engine->{$method}($query, $term);
        } catch (Throwable) {
            return $this->fallback->{$method}($query, $term);
        }
    }

    private function engine(): ?SearchEngine
    {
        $driver = config('search.driver', 'mariadb');
        $engineClass = config("search.drivers.{$driver}");

        if ($engineClass === null || $engineClass === MariaDbSearchService::class) {
            return $this->fallback;
        }

        try {
            $engine = $this->container->make($engineClass);
        } catch (Throwable) {
            return null;
        }

        return $engine instanceof SearchEngine ? $engine : null;
    }
}
