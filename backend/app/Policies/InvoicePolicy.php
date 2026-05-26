<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.view')
            || ($user->hasRole('student') && $this->studentVisibilityEnabled());
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('admin') || $user->can('invoices.view')) {
            return true;
        }

        return $user->hasRole('student')
            && $this->studentVisibilityEnabled()
            && (int) $invoice->student_id === (int) $user->id;
    }

    public function download(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('staff') && $user->can('invoices.view')) {
            return true;
        }

        return $user->hasRole('student')
            && $this->studentVisibilityEnabled()
            && (int) $invoice->student_id === (int) $user->id;
    }

    private function studentVisibilityEnabled(): bool
    {
        return (bool) config('billing.invoice.student_visibility_enabled', true);
    }
}
