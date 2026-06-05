<?php

namespace App\Notifications\Billing;

use App\Models\Invoice;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceEmailNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Invoice $invoice) {}

    /**
     * Select the delivery channels for invoice delivery.
     *
     * Laravel calls this when `InvoiceEmailService` sends an automatic or
     * manual invoice email. The notification only sends mail; portal history,
     * recipient status rows, invoice email metadata, and delivery failure logs
     * are written by the service around this notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the invoice email and PDF attachment.
     *
     * Laravel calls this during mail delivery. The method loads invoice context,
     * renders the PDF attachment, and returns a mail message. It does not write
     * logs or update statuses itself. Automatic sends are safe to retry after a
     * successful send because the service stores `automatic_sent_at`; manual
     * resends intentionally create a fresh delivery attempt.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->invoice->loadMissing(['student', 'subscription', 'courseProgram']);

        $pdfs = app(InvoicePdfService::class);

        return (new MailMessage)
            ->subject('Invoice '.$this->invoice->invoice_number)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('A new invoice is available for your Tutorvio account.')
            ->line('Invoice number: '.$this->invoice->invoice_number)
            ->line('Total amount: '.$this->money())
            ->line('Due date: '.$this->invoice->due_date->format('M j, Y'))
            ->attachData($pdfs->render($this->invoice), $pdfs->filename($this->invoice), [
                'mime' => 'application/pdf',
            ]);
    }

    private function money(): string
    {
        return $this->invoice->currency.' '.number_format((float) $this->invoice->total_amount, 2);
    }
}
