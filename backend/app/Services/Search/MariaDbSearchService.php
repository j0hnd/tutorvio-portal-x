<?php

namespace App\Services\Search;

use App\Contracts\Search\SearchEngine;
use Illuminate\Database\Eloquent\Builder;

class MariaDbSearchService implements SearchEngine
{
    public function available(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'mariadb';
    }

    public function students(Builder $query, ?string $term): Builder
    {
        return $this->usersByRole($query, 'student', $term);
    }

    public function teachers(Builder $query, ?string $term): Builder
    {
        return $this->usersByRole($query, 'teacher', $term);
    }

    public function lessons(Builder $query, ?string $term): Builder
    {
        $term = $this->normalize($term);

        if ($term === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('public_id', 'like', $this->like($term))
                ->orWhere('status', 'like', $this->like($term))
                ->orWhere('meeting_provider', 'like', $this->like($term))
                ->orWhere('notes', 'like', $this->like($term))
                ->orWhereHas('student', fn (Builder $query) => $query->searchIdentity($term))
                ->orWhereHas('teacher', fn (Builder $query) => $query->searchIdentity($term));
        });
    }

    public function lessonNotes(Builder $query, ?string $term): Builder
    {
        $term = $this->normalize($term);

        if ($term === null) {
            return $query;
        }

        $like = $this->like($term);

        return $query->where(function (Builder $query) use ($like, $term): void {
            $query
                ->where('lesson_objective', 'like', $like)
                ->orWhere('topics_covered', 'like', $like)
                ->orWhere('vocabulary_learned', 'like', $like)
                ->orWhere('grammar_focus', 'like', $like)
                ->orWhere('pronunciation_issues', 'like', $like)
                ->orWhere('student_speaking_confidence_observation', 'like', $like)
                ->orWhere('homework_assignment', 'like', $like)
                ->orWhere('recommendation_for_next_lesson', 'like', $like)
                ->orWhere('internal_note', 'like', $like)
                ->orWhere('review_note', 'like', $like)
                ->orWhereHas('student', fn (Builder $query) => $query->searchIdentity($term))
                ->orWhereHas('teacher', fn (Builder $query) => $query->searchIdentity($term));
        });
    }

    public function homework(Builder $query, ?string $term): Builder
    {
        $term = $this->normalize($term);

        if ($term === null) {
            return $query;
        }

        $like = $this->like($term);

        return $query->where(function (Builder $query) use ($like, $term): void {
            $query
                ->where('title', 'like', $like)
                ->orWhere('instructions', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('teacher_feedback', 'like', $like)
                ->orWhere('attachment_links', 'like', $like)
                ->orWhereHas('student', fn (Builder $query) => $query->searchIdentity($term))
                ->orWhereHas('teacher', fn (Builder $query) => $query->searchIdentity($term));
        });
    }

    public function resources(Builder $query, ?string $term): Builder
    {
        return $query->search($term);
    }

    public function announcements(Builder $query, ?string $term): Builder
    {
        return $query->search($term);
    }

    public function issues(Builder $query, ?string $term): Builder
    {
        return $query->search($term);
    }

    public function auditLogs(Builder $query, ?string $term): Builder
    {
        $term = $this->normalize($term);

        if ($term === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->search($term)
                ->orWhereHas('actor', fn (Builder $query) => $query->searchIdentity($term));

            if (is_numeric($term)) {
                $query->orWhere('target_entity_id', (int) $term);
            }
        });
    }

    private function usersByRole(Builder $query, string $role, ?string $term): Builder
    {
        return $query
            ->role($role)
            ->searchIdentity($term);
    }

    private function normalize(?string $term): ?string
    {
        $term = trim((string) $term);

        return $term === '' ? null : $term;
    }

    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }
}
