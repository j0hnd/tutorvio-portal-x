<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    public const VERSION_KEY = 'dashboard.summary.version';

    private const KEY_PREFIX = 'dashboard.summary';

    private const SUMMARY_TTL_SECONDS = 30;

    /**
     * Cache role-scoped dashboard summary data for a short interval.
     *
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function rememberSummary(User $user, ?string $role, callable $callback): array
    {
        return Cache::remember(
            $this->summaryKey($user, $role),
            self::SUMMARY_TTL_SECONDS,
            $callback
        );
    }

    public function refreshSummaryVersion(): void
    {
        Cache::forever(self::VERSION_KEY, $this->summaryVersion() + 1);
    }

    private function summaryKey(User $user, ?string $role): string
    {
        $permissionHash = md5($user->getAllPermissions()->pluck('name')->sort()->implode('|'));

        return implode(':', [
            self::KEY_PREFIX,
            'v'.$this->summaryVersion(),
            $role ?? 'none',
            $user->id,
            $permissionHash,
        ]);
    }

    private function summaryVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
