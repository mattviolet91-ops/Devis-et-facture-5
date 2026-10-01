<?php

namespace App\Notifications;

use App\Models\Appareil;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouvelAppareil extends Notification
{
    use Queueable;

    public function __construct(public User $compte, public Appareil $appareil) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Connexion depuis un nouvel appareil')
            ->greeting('Bonjour,')
            ->line($this->texte())
            ->line('Si ce n\'est pas vous, changez tout de suite le mot de passe de ce compte.')
            ->action('Ouvrir l\'application', route('accueil'))
            ->salutation('À bientôt.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => 'Nouvel appareil',
            'texte' => $this->texte(),
        ];
    }

    private function texte(): string
    {
        return sprintf(
            'Le compte %s s\'est connecté depuis un nouvel appareil (%s) le %s.',
            $this->compte->email,
            $this->appareil->libelle,
            $this->appareil->created_at->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
        );
    }
}
