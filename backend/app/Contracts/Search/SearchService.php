<?php

namespace App\Contracts\Search;

use Illuminate\Database\Eloquent\Builder;

interface SearchService
{
    public function students(Builder $query, ?string $term): Builder;

    public function teachers(Builder $query, ?string $term): Builder;

    public function lessons(Builder $query, ?string $term): Builder;

    public function lessonNotes(Builder $query, ?string $term): Builder;

    public function homework(Builder $query, ?string $term): Builder;

    public function resources(Builder $query, ?string $term): Builder;

    public function announcements(Builder $query, ?string $term): Builder;

    public function issues(Builder $query, ?string $term): Builder;

    public function auditLogs(Builder $query, ?string $term): Builder;
}
