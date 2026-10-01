<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\LienClient;
use App\Models\PaiementEnLigne;
use App\Support\Journal;
use App\Support\Montant;
use App\Support\Reglages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paiement en ligne par carte avec myPOS Checkout (API IPC v1.4).
 *
 * - Le client est envoyé sur la page de paiement myPOS : aucune donnée de carte ne passe par l'application.
 * - La requête est signée (RSA-SHA256) avec la clé privée du « pack de configuration ».
 * - Le paiement n'est enregistré qu'à la notification de serveur à serveur (IPCPurchaseNotify),
 *   après vérification de la signature avec le certificat myPOS, et une seule fois.
 * - Mode test : page de test myPOS, rien n'est enregistré, les clients ne voient pas le bouton.
 */
class MyPos
{
    public const URL_PRODUCTION = 'https://www.mypos.com/vmp/checkout';

    public const URL_TEST = 'https://www.mypos.com/vmp/checkout-test';

    public const VERSION = '1.4';

    public function __construct(private Reglages $reglages, private Encaissements $encaissements) {}

    public function mode(): string
    {
        return in_array(reglage('mypos.mode'), ['test', 'production'], true) && $this->pack() !== null
            ? (string) reglage('mypos.mode')
            : 'desactive';
    }

    /**
     * Le bouton « Payer par carte » est montré aux clients seulement en production.
     */
    public function visiblePourLesClients(): bool
    {
        return $this->mode() === 'production';
    }

    /**
     * Contenu du pack de configuration (stocké chiffré, jamais réaffiché).
     *
     * @return array{sid: string, cn: string, pk: string, pc: string, idx: string}|null
     */
    public function pack(): ?array
    {
        $texte = $this->reglages->getSecret('mypos.pack');

        return $texte ? self::lirePack($texte) : null;
    }

    /**
     * Lit un pack de configuration myPOS (base64 d'un JSON : sid, cn, pk, pc, idx).
     *
     * @return array{sid: string, cn: string, pk: string, pc: string, idx: string}|null
     */
    public static function lirePack(string $texte): ?array
    {
        $json = base64_decode(trim($texte), true);
        $donnees = $json !== false ? json_decode($json, true) : null;

        if (! is_array($donnees)) {
            return null;
        }
        foreach (['sid', 'cn', 'pk', 'pc', 'idx'] as $cle) {
            if (! isset($donnees[$cle]) || ! is_scalar($donnees[$cle]) || (string) $donnees[$cle] === '') {
                return null;
            }
        }
        if (! openssl_pkey_get_private((string) $donnees['pk']) || ! openssl_pkey_get_public((string) $donnees['pc'])) {
            return null;
        }

        return array_map('strval', array_intersect_key($donnees, array_flip(['sid', 'cn', 'pk', 'pc', 'idx'])));
    }

    /**
     * Prépare le formulaire envoyé à myPOS pour régler le reste à payer d'une facture.
     *
     * @return array{url: string, champs: array<string, string>}
     */
    public function preparer(Facture $facture, bool $essai): array
    {
        $pack = $this->pack();
        if (! $pack) {
            throw new \RuntimeException('Paiement en ligne non configuré.');
        }

        $montant = $facture->resteAPayer();
        if ($montant <= 0) {
            throw new \RuntimeException('Facture déjà réglée.');
        }

        $tentative = PaiementEnLigne::create([
            'facture_id' => $facture->id,
            'order_id' => 'F'.$facture->id.'-'.Str::upper(Str::random(12)),
            'montant' => $montant,
            'essai' => $essai,
        ]);

        $lien = LienClient::pour($facture);
        $base = rtrim((string) (config('app.client_url') ?: config('app.url')), '/');
        $euros = number_format($montant / 100, 2, '.', '');
        $libelle = Str::limit(Str::ascii($facture->libelleType().' '.$facture->numero), 60, '');

        $champs = [
            'IPCmethod' => 'IPCPurchase',
            'IPCVersion' => self::VERSION,
            'IPCLanguage' => 'FR',
            'SID' => $pack['sid'],
            'WalletNumber' => $pack['cn'],
            'Amount' => $euros,
            'Currency' => 'EUR',
            'OrderID' => $tentative->order_id,
            'URL_OK' => $lien->url('/paiement/merci?commande='.$tentative->order_id),
            'URL_Cancel' => $lien->url('/paiement/annule?commande='.$tentative->order_id),
            'URL_Notify' => $base.'/paiement/mypos/notification',
            'CardTokenRequest' => '0',
            'KeyIndex' => $pack['idx'],
            'PaymentParametersRequired' => '3',
            'CartItems' => '1',
            'Article_1' => $libelle,
            'Quantity_1' => '1',
            'Price_1' => $euros,
            'Amount_1' => $euros,
            'Currency_1' => 'EUR',
            'Note' => '',
        ];
        $champs['Signature'] = self::signer($champs, $pack['pk']);

        return ['url' => $essai ? self::URL_TEST : self::URL_PRODUCTION, 'champs' => $champs];
    }

    /**
     * Signature myPOS : valeurs jointes par « - », encodées en base64, signées RSA-SHA256, encodées en base64.
     *
     * @param  array<string, string>  $champs
     */
    public static function signer(array $champs, string $clePrivee): string
    {
        unset($champs['Signature']);
        openssl_sign(base64_encode(implode('-', $champs)), $signature, $clePrivee, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $champs
     */
    public static function verifier(array $champs, string $certificat): bool
    {
        $signature = base64_decode((string) ($champs['Signature'] ?? ''), true);
        unset($champs['Signature']);

        if ($signature === false || $signature === '') {
            return false;
        }

        $donnees = base64_encode(implode('-', array_map('strval', $champs)));

        return openssl_verify($donnees, $signature, $certificat, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Notification de serveur à serveur. Renvoie true si elle est valable (myPOS attend alors « OK »).
     *
     * @param  array<string, mixed>  $champs
     */
    public function notification(array $champs): bool
    {
        $pack = $this->pack();
        if (! $pack || ! self::verifier($champs, $pack['pc'])) {
            Log::warning('Notification myPOS refusée : signature invalide.');

            return false;
        }
        if (($champs['IPCmethod'] ?? '') !== 'IPCPurchaseNotify' || ($champs['SID'] ?? '') !== $pack['sid']) {
            return false;
        }

        $tentative = PaiementEnLigne::where('order_id', (string) ($champs['OrderID'] ?? ''))->first();
        if (! $tentative) {
            return false;
        }

        $montant = Montant::lire(str_replace('.', ',', (string) ($champs['Amount'] ?? '')));
        if ($montant !== $tentative->montant || ($champs['Currency'] ?? '') !== 'EUR') {
            Log::warning('Notification myPOS refusée : montant différent pour '.$tentative->order_id);

            return false;
        }

        // Déjà traitée (myPOS peut renvoyer la même notification) : on répond OK sans rien refaire.
        if ($tentative->statut === 'payee') {
            return true;
        }

        DB::transaction(function () use ($tentative, $champs) {
            $tentative = PaiementEnLigne::whereKey($tentative->id)->lockForUpdate()->first();
            if ($tentative->statut === 'payee') {
                return;
            }

            if ($tentative->essai) {
                // Mode test : aucun argent réel, rien n'est enregistré dans la comptabilité.
                $tentative->update(['statut' => 'payee', 'transaction' => (string) ($champs['IPC_Trnref'] ?? '')]);
                Journal::ecrire('paiement.essai', 'Paiement d\'essai myPOS réussi ('.$tentative->order_id.'), non enregistré');

                return;
            }

            $facture = $tentative->facture;
            $montant = min($tentative->montant, $facture->resteAPayer());
            $paiement = $montant > 0
                ? $this->encaissements->enregistrer($facture, $montant, 'carte_en_ligne', now()->toDateString(), (string) ($champs['IPC_Trnref'] ?? $tentative->order_id), 'Paiement en ligne myPOS', null)
                : null;

            $tentative->update(['statut' => 'payee', 'transaction' => (string) ($champs['IPC_Trnref'] ?? ''), 'paiement_id' => $paiement?->id]);
            Encaissements::prevenirGerant($facture, 'Paiement en ligne de '.Montant::formater($tentative->montant).' reçu pour la facture '.$facture->numero.' ('.$facture->client->nomComplet().').');
        });

        return true;
    }

    /**
     * Annulation par le client : seulement la tentative de la facture du lien.
     */
    public function annuler(Facture $facture, string $orderId): void
    {
        PaiementEnLigne::where('order_id', $orderId)
            ->where('facture_id', $facture->id)
            ->where('statut', 'en_attente')
            ->update(['statut' => 'annulee']);
    }
}
