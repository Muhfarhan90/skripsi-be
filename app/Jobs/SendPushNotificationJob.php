<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $notificationId)
    {
    }

    public function handle(FcmService $fcmService): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $fcmService->sendNotification($notification);
    }
}
