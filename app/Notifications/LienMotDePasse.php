<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LienMotDePasse extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lien = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Choisir un nouveau mot de passe')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé à changer votre mot de passe.')
            ->action('Choisir un nouveau mot de passe', $lien)
            ->line("Ce lien marche pendant {$minutes} minutes.")
            ->line('Si vous n\'avez rien demandé, ne faites rien : votre mot de passe reste le même.')
            ->salutation('À bientôt.');
    }
}
