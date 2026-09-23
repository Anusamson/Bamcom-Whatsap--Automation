<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AutomationNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $data = []
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
