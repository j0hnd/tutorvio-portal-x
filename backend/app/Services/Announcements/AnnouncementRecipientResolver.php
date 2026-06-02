<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnouncementRecipientResolver
{
    /**
     * Resolve the active users targeted by an announcement.
     *
     * The announcement model is loaded with its targets, and the returned
     * payload preserves which target rules matched each recipient.
     *
     * @return Collection<int, array{user_id: int, matched_targets: array<int, array<string, mixed>>}>
     */
    public function resolve(Announcement $announcement): Collection
    {
        $announcement->loadMissing('targets');

        $recipients = collect();

        foreach ($announcement->targets as $target) {
            $this->usersForTarget($target)
                ->each(function (User $user) use ($recipients, $target) {
                    $recipient = $recipients->get($user->id, [
                        'user_id' => $user->id,
                        'matched_targets' => [],
                    ]);

                    $recipient['matched_targets'][] = [
                        'type' => $target->target_type,
                        'target_id' => $target->target_id,
                        'user_id' => $target->user_id,
                        'role' => $target->role,
                        'group' => $this->targetGroup($target),
                    ];

                    $recipients->put($user->id, $recipient);
                });
        }

        return $recipients->values();
    }

    /**
     * Synchronize the stored recipient rows for an announcement.
     *
     * This deletes existing recipient rows and recreates them from the current
     * announcement targets inside a transaction.
     */
    public function syncRecipients(Announcement $announcement): int
    {
        $resolved = $this->resolve($announcement);

        DB::transaction(function () use ($announcement, $resolved) {
            $announcement->recipients()->delete();

            $resolved->chunk(500)->each(function (Collection $chunk) use ($announcement) {
                $announcement->recipients()->createMany($chunk->all());
            });
        });

        return $resolved->count();
    }

    /**
     * Determine whether a user can see an announcement through recipient scope.
     *
     * Staff users must also have operational-notice visibility before recipient
     * membership is considered.
     */
    public function canView(Announcement $announcement, User $user): bool
    {
        if (! $this->staffHasAnnouncementVisibility($user)) {
            return false;
        }

        return $announcement->recipients()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function usersForTarget(AnnouncementTarget $target): Collection
    {
        return match ($target->target_type) {
            AnnouncementTarget::TARGET_ALL => $this->baseUserQuery()->get(),
            AnnouncementTarget::TARGET_ROLE => $this->baseUserQuery()
                ->role($target->role)
                ->get(),
            AnnouncementTarget::TARGET_USER => $this->baseUserQuery()
                ->where('id', $target->user_id ?? $target->target_id)
                ->get(),
            AnnouncementTarget::TARGET_COURSE,
            AnnouncementTarget::TARGET_COURSE_PROGRAM => $this->courseProgramUsers($target),
            AnnouncementTarget::TARGET_COURSE_TYPE => $this->courseTypeUsers($target),
            AnnouncementTarget::TARGET_TEACHER_GROUP => $this->teacherGroupUsers($target),
            AnnouncementTarget::TARGET_STUDENT_GROUP => $this->studentGroupUsers($target),
            default => collect(),
        };
    }

    private function baseUserQuery(): Builder
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function (Builder $query) {
                $query
                    ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'staff'))
                    ->orWhere(fn (Builder $query) => $query
                        ->role('staff')
                        ->permission('dashboard.operational_notices.view'));
            });
    }

    private function courseProgramUsers(AnnouncementTarget $target): Collection
    {
        return $this->baseUserQuery()
            ->where(function (Builder $query) use ($target) {
                $query
                    ->whereHas('courseProgramAssignments', fn (Builder $query) => $query
                        ->active()
                        ->where('course_program_id', $target->target_id))
                    ->orWhereHas('assignedStudents', fn (Builder $query) => $query
                        ->whereHas('user.courseProgramAssignments', fn (Builder $query) => $query
                            ->active()
                            ->where('course_program_id', $target->target_id)));
            })
            ->get();
    }

    private function courseTypeUsers(AnnouncementTarget $target): Collection
    {
        return $this->baseUserQuery()
            ->where(function (Builder $query) use ($target) {
                $query
                    ->whereHas('courseProgramAssignments.courseProgram', fn (Builder $query) => $query
                        ->where('course_type_id', $target->target_id))
                    ->orWhereHas('assignedStudents.user.courseProgramAssignments.courseProgram', fn (Builder $query) => $query
                        ->where('course_type_id', $target->target_id));
            })
            ->get();
    }

    private function teacherGroupUsers(AnnouncementTarget $target): Collection
    {
        $group = $this->targetGroup($target);

        if ($group === null) {
            return collect();
        }

        return $this->baseUserQuery()
            ->role('teacher')
            ->whereHas('teacherProfile', fn (Builder $query) => $query
                ->where('specialization', $group)
                ->orWhere('internal_status', $group))
            ->get();
    }

    private function studentGroupUsers(AnnouncementTarget $target): Collection
    {
        $group = $this->targetGroup($target);

        if ($group === null) {
            return collect();
        }

        return $this->baseUserQuery()
            ->role('student')
            ->whereHas('studentProfile', fn (Builder $query) => $query
                ->where('class_type', $group)
                ->orWhere('course', $group))
            ->get();
    }

    private function targetGroup(AnnouncementTarget $target): ?string
    {
        $group = $target->metadata['group'] ?? $target->metadata['name'] ?? null;

        return is_string($group) && $group !== '' ? $group : null;
    }

    private function staffHasAnnouncementVisibility(User $user): bool
    {
        return ! $user->hasRole('staff')
            || $user->can('dashboard.operational_notices.view');
    }
}
