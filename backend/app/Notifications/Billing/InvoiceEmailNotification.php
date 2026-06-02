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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

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
