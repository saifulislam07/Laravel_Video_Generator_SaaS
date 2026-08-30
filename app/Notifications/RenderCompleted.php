<?php

namespace App\Notifications;

use App\Models\VideoRender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenderCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VideoRender $render) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->render->project;

        return (new MailMessage)
            ->subject("Your video “{$project->title}” is ready")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your video for “{$project->title}” has finished rendering.")
            ->action('Watch & download', route('dashboard'))
            ->line('Note: download it soon — Shotstack CDN links are temporary.');
    }
}
