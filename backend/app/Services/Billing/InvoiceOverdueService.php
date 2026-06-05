<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class InvoiceOverdueService
{
    /**
     * Mark unpaid invoices past their due date as overdue.
     *
     * This scheduled-task handler runs from the daily `invoices:mark-overdue`
     * command. The comparison uses the application timezone and updates matching
     * invoice statuses in bulk. It does not send email or portal notifications,
     * and it does not write logs. It is safe to retry because only invoices that
     * are still unpaid and not already overdue are updated.
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
