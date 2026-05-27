<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogs\ListAuditLogsRequest;
use App\Http\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $validated = $request->validated();
        $actorUserId = $validated['actor_user_id'] ?? $validated['user_id'] ?? null;

        $auditLogs = AuditLog::query()
            ->with(['actor.roles'])
            ->when($actorUserId, fn (Builder $query, int $id) => $query->where('actor_user_id', $id))
            ->when($validated['action_type'] ?? null, fn (Builder $query, string $value) => $query->where('action_type', $value))
            ->when($validated['module'] ?? null, fn (Builder $query, string $value) => $query->where('module', $value))
            ->when($validated['target_entity_type'] ?? null, fn (Builder $query, string $value) => $query->where('target_entity_type', $value))
            ->when($validated['target_entity_id'] ?? null, fn (Builder $query, int $id) => $query->where('target_entity_id', $id))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('action_type', 'like', '%'.$search.'%')
                        ->orWhere('module', 'like', '%'.$search.'%')
                        ->orWhere('target_entity_type', 'like', '%'.$search.'%')
                        ->orWhere('ip_address', 'like', '%'.$search.'%')
                        ->orWhere('user_agent', 'like', '%'.$search.'%')
                        ->orWhere('metadata', 'like', '%'.$search.'%')
                        ->orWhereHas('actor', fn (Builder $query) => $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));

                    if (is_numeric($search)) {
                        $query->orWhere('target_entity_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $auditLogs->through(fn (AuditLog $auditLog) => new AuditLogResource($auditLog))
        );
    }
}
