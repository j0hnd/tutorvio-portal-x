<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoiceOverdueService
{
    /**
     * Mark unpaid invoices past their due date as overdue.
     *
     * The comparison uses the application timezone and updates matching invoice
     * statuses in bulk without emitting per-invoice notifications.
     */
    public function markOverdue(?Carbon $asOf = null): int
    {
        $today = ($asOf ?? Carbon::now(config('app.timezone')))->copy()->timezone(config('app.timezone'))->toDateString();

        return Invoice::query()
            ->where('status', Invoice::STATUS_UNPAID)
            ->whereNull('paid_date')
            ->whereDate('due_date', '<', $today)
            ->update(['status' => Invoice::STATUS_OVERDUE]);
    }
}
