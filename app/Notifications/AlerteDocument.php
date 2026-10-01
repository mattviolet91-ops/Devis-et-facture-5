<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte au gérant sur un document (devis signé, refusé, modification demandée, paiement reçu…).
 */
class AlerteDocument extends Notification
{
    use Queueable;

    public function __construct(public string $titre, public string $texte, public ?string $lien = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->titre)->greeting('Bonjour,')->line($this->texte);

        if ($this->lien) {
            $message->action('Voir dans l\'application', $this->lien);
        }

        return $message->salutation('À bientôt.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['titre' => $this->titre, 'texte' => $this->texte, 'lien' => $this->lien];
    }
}
