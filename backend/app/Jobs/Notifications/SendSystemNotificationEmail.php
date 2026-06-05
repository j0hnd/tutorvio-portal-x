<?php

namespace App\Jobs\Notifications;

use App\Services\Notifications\SystemNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSystemNotificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $notificationRecipientId) {}

    public function handle(SystemNotificationService $notifications): void
    {
        $notifications->sendQueuedEmail($this->notificationRecipientId);
    }
}
