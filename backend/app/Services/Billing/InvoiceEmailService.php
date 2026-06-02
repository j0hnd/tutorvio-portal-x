<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\Billing\InvoiceEmailNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceEmailService
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    /**
     * Send an invoice email when automatic invoice delivery is enabled.
     *
     * The method skips invoices already marked as automatically sent, creates
     * notification history for attempts, updates invoice email metadata, and
     * records failed delivery details instead of throwing mail exceptions.
     */
    public function sendAutomatically(Invoice $invoice): bool
    {
        if (! (bool) config('billing.invoice.email.automatic_enabled', false)) {
            return false;
        }

        if ($this->automaticEmailAlreadySent($invoice)) {
            return false;
        }

        return $this->send($invoice, self::MODE_AUTOMATIC);
    }

    /**
     * Resend an invoice email as a manual staff action.
     *
     * The method records the sender on notification history when provided,
     * updates invoice email metadata, and records missing-recipient or delivery
     * failures as failed notification recipients.
     */
    public function resendManually(Invoice $invoice, ?User $sender = null): bool
    {
        return $this->send($invoice, self::MODE_MANUAL, $sender);
    }

    private function send(Invoice $invoice, string $mode, ?User $sender = null): bool
    {
        $invoice->loadMissing('student');
        $student = $invoice->student;

        if ($student === null || ! $student->email) {
            $this->recordMissingRecipient($invoice, $mode, $sender);

            return false;
        }

        $notification = $this->createHistoryRecord($invoice, $mode, $sender);

        try {
            $student->notify(new InvoiceEmailNotification($invoice));

            $this->recordDelivery($notification, $student, $invoice, $mode, true);
            $this->recordInvoiceEmailState($invoice, $mode, true);

            return true;
        } catch (Throwable $exception) {
            $this->recordDelivery($notification, $student, $invoice, $mode, false, $exception);
            $this->recordInvoiceEmailState($invoice, $mode, false, $exception);

            Log::warning('Invoice email delivery failed.', [
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'mode' => $mode,
                'failure_type' => $exception::class,
            ]);

            return false;
        }
    }

    private function automaticEmailAlreadySent(Invoice $invoice): bool
    {
        return (bool) data_get($invoice->metadata, 'email.automatic_sent_at');
    }

    private function createHistoryRecord(Invoice $invoice, string $mode, ?User $sender): Notification
    {
        return Notification::create([
            'title' => 'Invoice '.$invoice->invoice_number,
            'body' => 'Invoice email sent to '.$invoice->student->email.'.',
            'type' => Notification::TYPE_EMAIL,
            'sender_id' => $sender?->id,
            'published_at' => now(),
            'metadata' => [
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'mode' => $mode,
            ],
        ]);
    }

    private function recordMissingRecipient(Invoice $invoice, string $mode, ?User $sender): void
    {
        $notification = Notification::create([
            'title' => 'Invoice '.$invoice->invoice_number,
            'body' => 'Invoice email could not be sent because the student has no email address.',
            'type' => Notification::TYPE_EMAIL,
            'sender_id' => $sender?->id,
            'published_at' => now(),
            'metadata' => [
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'mode' => $mode,
                'failure_type' => 'missing_recipient_email',
            ],
        ]);

        NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $invoice->student_id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_FAILED,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'mode' => $mode,
                'failure_type' => 'missing_recipient_email',
            ],
        ]);

        $this->recordInvoiceEmailState($invoice, $mode, false);
    }

    private function recordDelivery(
        Notification $notification,
        User $student,
        Invoice $invoice,
        string $mode,
        bool $sent,
        ?Throwable $exception = null,
    ): void {
        NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => $sent
                ? NotificationRecipient::STATUS_SENT
                : NotificationRecipient::STATUS_FAILED,
            'sent_at' => $sent ? now() : null,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'mode' => $mode,
                ...($exception === null ? [] : ['failure_type' => $exception::class]),
            ],
        ]);
    }

    private function recordInvoiceEmailState(Invoice $invoice, string $mode, bool $sent, ?Throwable $exception = null): void
    {
        $now = Carbon::now()->toISOString();
        $metadata = $invoice->metadata ?? [];
        $email = data_get($metadata, 'email', []);

        $email = [
            ...$email,
            'last_status' => $sent ? NotificationRecipient::STATUS_SENT : NotificationRecipient::STATUS_FAILED,
            'last_mode' => $mode,
            'last_attempted_at' => $now,
            ...($sent ? ['last_sent_at' => $now] : []),
            ...($exception === null ? [] : ['last_failure_type' => $exception::class]),
        ];

        if ($sent && $mode === self::MODE_AUTOMATIC) {
            $email['automatic_sent_at'] = $email['automatic_sent_at'] ?? $now;
        }

        data_set($metadata, 'email', $email);

        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);

        $invoice->forceFill(['metadata' => $metadata]);
    }
}
