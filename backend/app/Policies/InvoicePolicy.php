<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can list invoices.
     *
     * Admins can list invoices. Staff need `invoices.view`. Students can list
     * their invoices only when `billing.invoice.student_visibility_enabled` is
     * enabled; otherwise students are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('invoices.view'))
            || ($user->hasRole('student') && $this->studentVisibilityEnabled());
    }

    /**
     * Determine whether the user can view a specific invoice.
     *
     * Admins can view any invoice. Staff need `invoices.view`. Students can
     * view only their own invoice when student invoice visibility is enabled.
     * Other students and hidden student invoices are denied.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('admin') || ($user->hasRole('staff') && $user->can('invoices.view'))) {
            return true;
        }

        return $user->hasRole('student')
            && $this->studentVisibilityEnabled()
            && (int) $invoice->student_id === (int) $user->id;
    }

    /**
     * Determine whether the user can download an invoice.
     *
     * Download access mirrors invoice viewing: admins can download all
     * invoices, staff need `invoices.view`, and students are limited to their
     * own invoices while student invoice visibility is enabled.
     */
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

    /**
     * Determine whether the user can send an invoice email.
     *
     * Admins can send invoice emails. Staff need the Spatie permission
     * `invoices.create`. Students and teachers are denied.
     */
    public function sendEmail(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('invoices.create'));
    }

    /**
     * Determine whether the user can update invoice payment status.
     *
     * Admins can update payment status. Staff need `invoices.update`.
     * Students and teachers are denied.
     */
    public function updatePaymentStatus(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('invoices.update'));
    }

    /**
     * Determine whether student-facing invoice visibility is enabled.
     *
     * When `billing.invoice.student_visibility_enabled` is false, students are
     * denied invoice list, detail, and download access regardless of ownership.
     */
    private function studentVisibilityEnabled(): bool
    {
        return (bool) config('billing.invoice.student_visibility_enabled', true);
    }
}
