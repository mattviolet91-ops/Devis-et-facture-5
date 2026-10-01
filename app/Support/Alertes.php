<?php

namespace App\Support;

use App\Models\User;
use App\Services\NotificationsTelephone;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Envoie une alerte : elle est TOUJOURS enregistrée dans l'application (accueil),
 * puis l'email et la notification sur le téléphone sont tentés ; un échec d'email (Gmail pas encore réglé…) ne bloque rien.
 */
class Alertes
{
    /**
     * @param  iterable<User>  $destinataires
     */
    public static function envoyer(iterable $destinataires, Notification $alerte): void
    {
        foreach ($destinataires as $destinataire) {
            $destinataire->notifyNow($alerte, ['database']);

            try {
                $destinataire->notifyNow($alerte, ['mail']);
            } catch (\Throwable $e) {
                Log::warning('Email d\'alerte non envoyé ('.class_basename($alerte).') : '.$e->getMessage());
            }

            // Notification sur le téléphone, si la personne l'a activée.
            if (method_exists($alerte, 'toArray')) {
                try {
                    $donnees = $alerte->toArray($destinataire);
                    if (! empty($donnees['titre'])) {
                        app(NotificationsTelephone::class)->envoyer($destinataire, (string) $donnees['titre'], (string) ($donnees['texte'] ?? ''), $donnees['lien'] ?? null);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Notification téléphone non envoyée : '.$e->getMessage());
                }
            }
        }
    }
}
