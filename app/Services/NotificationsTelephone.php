<?php

namespace App\Services;

use App\Models\AbonnementPush;
use App\Models\User;
use App\Support\Reglages;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Notifications sur le téléphone (Web Push), sans service extérieur payant.
 * Les clés VAPID sont créées automatiquement ; la clé privée est chiffrée en base.
 */
class NotificationsTelephone
{
    public function __construct(private Reglages $reglages) {}

    /**
     * Clé publique à donner au navigateur (créée au premier besoin).
     */
    public function clePublique(): ?string
    {
        if (! $this->reglages->get('push.cle_publique') || ! $this->reglages->aSecret('push.cle_privee')) {
            try {
                $cles = VAPID::createVapidKeys();
            } catch (\Throwable $e) {
                Log::warning('Clés de notification impossibles à créer : '.$e->getMessage());

                return null;
            }
            $this->reglages->set('push.cle_publique', $cles['publicKey']);
            $this->reglages->setSecret('push.cle_privee', $cles['privateKey']);
        }

        return (string) $this->reglages->get('push.cle_publique');
    }

    /**
     * Envoie une notification à tous les téléphones abonnés de la personne.
     * Les abonnements expirés sont retirés. Renvoie le nombre d'envois réussis.
     */
    public function envoyer(User $user, string $titre, string $texte, ?string $lien = null): int
    {
        $abonnements = AbonnementPush::where('user_id', $user->id)->get();
        if ($abonnements->isEmpty() || ! $this->clePublique()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => 'mailto:'.((string) $this->reglages->get('identite.email') ?: 'contact@localhost.invalid'),
            'publicKey' => $this->clePublique(),
            'privateKey' => (string) $this->reglages->getSecret('push.cle_privee'),
        ]], ['TTL' => 3600], 10);

        $contenu = json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => $lien], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($abonnements as $abonnement) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $abonnement->endpoint,
                'publicKey' => $abonnement->cle_p256dh,
                'authToken' => $abonnement->cle_auth,
            ]), $contenu);
        }

        $reussis = 0;
        foreach ($webPush->flush() as $rapport) {
            if ($rapport->isSuccess()) {
                $reussis++;
            } elseif ($rapport->isSubscriptionExpired()) {
                AbonnementPush::where('endpoint', $rapport->getEndpoint())->delete();
            }
        }

        return $reussis;
    }
}
