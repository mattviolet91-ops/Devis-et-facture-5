<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssuranceBientotFinie extends Notification
{
    use Queueable;

    public function __construct(public CarbonImmutable $dateFin, public int $joursRestants) {}

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
            ->subject($this->joursRestants < 0 ? 'Assurance décennale expirée' : 'Assurance décennale : bientôt à renouveler')
            ->greeting('Bonjour,')
            ->line($this->texte())
            ->line('Pensez à demander la nouvelle attestation à votre assureur, puis à l\'ajouter dans Réglages → Assurance décennale.')
            ->action('Ouvrir les réglages', route('reglages.edit', 'assurance'))
            ->salutation('À bientôt.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['titre' => 'Assurance décennale', 'texte' => $this->texte()];
    }

    private function texte(): string
    {
        $date = $this->dateFin->format('d/m/Y');

        return match (true) {
            $this->joursRestants < 0 => "Votre assurance décennale a pris fin le {$date}. Vos devis ne doivent pas mentionner une assurance expirée.",
            $this->joursRestants === 0 => "Votre assurance décennale prend fin aujourd'hui ({$date}).",
            default => "Votre assurance décennale prend fin le {$date}, dans {$this->joursRestants} jour(s).",
        };
    }
}
