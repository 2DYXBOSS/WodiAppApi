<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminEventMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $message, private readonly ?string $link) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[Maison WODI] '.$this->title)->greeting($this->title)->line($this->message);
        if ($this->link) {
            $mail->line('Back-office : '.$this->link);
        }

        return $mail;
    }
}
