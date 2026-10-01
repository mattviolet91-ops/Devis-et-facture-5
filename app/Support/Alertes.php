<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Envoie une alerte : elle est TOUJOURS enregistrée dans l'application (accueil),
 * puis l'email est tenté ; un échec d'email (Gmail pas encore réglé…) ne bloque rien.
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
        }
    }
}
