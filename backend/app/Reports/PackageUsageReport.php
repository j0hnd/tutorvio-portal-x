<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\Subscription;
use App\Models\User;
use App\Reports\Concerns\PaginatesReportQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PackageUsageReport
{
    use PaginatesReportQueries;

    /**
     * @return array{
     *     summary: array{total_active_packages: int, total_consumed_lessons: int, total_remaining_lessons: int, expiring_package_count: int, frozen_package_count: int},
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters, User $viewer, array $pagination = []): array
    {
        $query = $this->baseQuery($filters);
        $subscriptions = (clone $query)->get();
        $canViewBilling = $this->canViewBillingReferences($viewer);
        $page = $this->pageRows($query, $pagination, fn (Subscription $subscription) => $this->row($subscription, $canViewBilling));

        return [
            'summary' => $this->summary($subscriptions),
            'rows' => $page['rows'],
            'total' => $page['total'],
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<Subscription>
     */
    private function baseQuery(SchoolReportFilters $filters): Builder
    {
        $query = Subscription::query()
            ->with([
                'invoice:id,invoice_number',
                'student:id,public_id,name,email',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,public_id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
            ]);

        $filters->applyTo($query, [
            'date_column' => 'starts_at',
            'student_column' => 'user_id',
            'teacher_column' => null,
            'course_relation' => 'student.courseProgramAssignments',
            'status_column' => 'status',
        ]);

        return $query
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return array{total_active_packages: int, total_consumed_lessons: int, total_remaining_lessons: int, expiring_package_count: int, frozen_package_count: int}
     */
    private function summary(Collection $subscriptions): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $expirationThreshold = $today->copy()->addDays(7)->endOfDay();

        return [
            'total_active_packages' => $subscriptions
                ->where('status', Subscription::STATUS_ACTIVE)
                ->count(),
            'total_consumed_lessons' => $subscriptions->sum('consumed_lesson_count'),
            'total_remaining_lessons' => $subscriptions->sum('remaining_lesson_count'),
            'expiring_package_count' => $subscriptions
                ->filter(fn (Subscription $subscription) => $subscription->ends_at !== null
                    && $subscription->status === Subscription::STATUS_ACTIVE
                    && $subscription->ends_at->betweenIncluded($today, $expirationThreshold))
                ->count(),
            'frozen_package_count' => $subscriptions
                ->where('is_frozen', true)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Subscription $subscription, bool $canViewBilling): array
    {
        $row = [
            'student_id' => $subscription->student?->public_id,
            'student_name' => $subscription->student?->name,
            'student' => [
                'id' => $subscription->student?->public_id,
                'name' => $subscription->student?->name,
            ],
            'package_id' => $subscription->public_id,
            'subscription_id' => $subscription->public_id,
            'package_name' => $subscription->plan_name,
            'subscription_name' => $subscription->plan_name,
            'package_status' => $subscription->is_frozen ? 'frozen' : $subscription->status,
            'status' => $subscription->status,
            'is_frozen' => $subscription->is_frozen,
            'lesson_balance' => $subscription->total_lesson_count,
            'consumed_lessons' => $subscription->consumed_lesson_count,
            'remaining_lessons' => $subscription->remaining_lesson_count,
            'starts_at' => $subscription->starts_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
            'course' => $this->course($subscription),
        ];

        if ($canViewBilling) {
            $row['payment_status'] = $subscription->payment_status;
            $row['invoice_reference'] = $subscription->invoice_reference ?? $subscription->invoice?->invoice_number;
        }

        return $row;
    }

    /**
     * Determine whether package-usage billing references can be included.
     *
     * Admins can view billing references. Staff need `invoices.view`.
     * Teachers, students, and staff without permission receive rows without
     * payment status or invoice references.
     */
    private function canViewBillingReferences(User $viewer): bool
    {
        return $viewer->hasRole('admin')
            || ($viewer->hasRole('staff') && $viewer->can('invoices.view'));
    }

    /**
     * @return array{id: string|null, title: string|null, placement_level: string|null}|null
     */
    private function course(Subscription $subscription): ?array
    {
        /** @var Collection<int, CourseProgramStudentAssignment>|null $assignments */
        $assignments = $subscription->student?->courseProgramAssignments;
        $courseProgram = $assignments?->first()?->courseProgram;

        if ($courseProgram === null) {
            return null;
        }

        return [
            'id' => $courseProgram->public_id,
            'title' => $courseProgram->title,
            'placement_level' => $courseProgram->placement_level,
        ];
    }
}
