<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Lien de réinitialisation ou d'activation de compte (invitation). */
class PasswordLinkMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url, private readonly bool $invitation = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = $this->invitation ? 60 * 24 * 7 : (int) config('wodi.reset_password.ttl');

        return (new MailMessage)
            ->subject($this->invitation ? 'Votre accès Maison WODI' : 'Réinitialisation de votre mot de passe')
            ->greeting('Bonjour '.$notifiable->first_name.',')
            ->line($this->invitation
                ? 'Un compte Maison WODI a été créé pour vous. Définissez votre mot de passe pour l’activer.'
                : 'Vous avez demandé à réinitialiser votre mot de passe.')
            ->action($this->invitation ? 'Définir mon mot de passe' : 'Choisir un nouveau mot de passe', $this->url)
            ->line("Ce lien est valable {$minutes} minutes et ne peut être utilisé qu’une fois.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.');
    }
}
