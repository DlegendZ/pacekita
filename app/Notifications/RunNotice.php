<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RunNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public string $title, public string $message, public int $occurrenceId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array { return ['database', 'mail']; }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'occurrence_id' => $this->occurrenceId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->message)->action('View run', route('runs.show', $this->occurrenceId));
    }
}
