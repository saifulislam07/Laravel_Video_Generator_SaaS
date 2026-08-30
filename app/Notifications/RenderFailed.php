<?php

namespace App\Notifications;

use App\Models\VideoRender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenderFailed extends Notification implements ShouldQueue
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
            ->error()
            ->subject("Your video “{$project->title}” could not be rendered")
            ->greeting("Hi {$notifiable->name},")
            ->line("We hit a problem rendering “{$project->title}”.")
            ->line($this->render->error_message ?: 'The render failed at Shotstack.')
            ->line('Your credit has not been affected — you can try again.')
            ->action('Open the project', route('projects.builder', $project));
    }
}
