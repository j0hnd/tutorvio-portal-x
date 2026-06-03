<?php

namespace App\Jobs\Billing;

use App\Models\User;
use App\Services\Billing\InvoiceEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $invoiceId,
        public readonly string $mode,
        public readonly ?int $senderId = null,
    ) {}

    public function handle(InvoiceEmailService $emails): void
    {
        $emails->sendQueued(
            $this->invoiceId,
            $this->mode,
            $this->senderId ? User::query()->find($this->senderId) : null
        );
    }
}
