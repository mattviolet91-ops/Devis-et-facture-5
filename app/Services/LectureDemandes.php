<?php

namespace App\Services;

use App\Models\Demande;
use App\Models\EmailRecu;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Support\Alertes;
use App\Support\Telephone;
use Illuminate\Support\Str;

/**
 * Range les emails reçus et repère les demandes envoyées par le site (formulaire WordPress).
 */
class LectureDemandes
{
    /** Emails gardés pour l'écran « Mes derniers emails ». */
    public const GARDER = 50;

    /**
     * @param  list<array{message_id: string, expediteur: ?string, expediteur_email: ?string, sujet: string, texte: string, date: \DateTimeInterface}>  $emails
     * @return int nombre de nouvelles demandes
     */
    public function importer(array $emails): int
    {
        $nouvelles = [];

        foreach ($emails as $email) {
            $id = Str::limit($email['message_id'], 190, '');
            if (EmailRecu::where('message_id', $id)->exists()) {
                continue;
            }

            $estDemande = self::estDemande($email);
            EmailRecu::create([
                'message_id' => $id,
                'expediteur' => Str::limit((string) $email['expediteur'], 150, ''),
                'expediteur_email' => Str::limit((string) $email['expediteur_email'], 150, ''),
                'sujet' => Str::limit($email['sujet'], 250),
                'extrait' => Str::limit(Str::squish($email['texte']), 500),
                'est_demande' => $estDemande,
                'recu_at' => $email['date'],
            ]);

            if ($estDemande && ! Demande::where('message_id', $id)->exists()) {
                $nouvelles[] = Demande::create(self::analyser($email['texte']) + [
                    'source' => 'email', 'message_id' => $id, 'recue_at' => $email['date'],
                ]);
            }
        }

        // On ne garde que les derniers emails (simple aperçu).
        $garder = EmailRecu::orderByDesc('recu_at')->limit(self::GARDER)->pluck('id');
        EmailRecu::whereNotIn('id', $garder)->delete();

        if ($nouvelles) {
            self::alerter(count($nouvelles));
        }

        return count($nouvelles);
    }

    /**
     * Email envoyé par le formulaire du site ? (mot du réglage dans l'expéditeur, l'objet ou la fin du message)
     *
     * @param  array{sujet: string, expediteur: ?string, expediteur_email: ?string, texte: string}  $email
     */
    public static function estDemande(array $email): bool
    {
        $filtre = Str::lower(trim((string) reglage('suivi.imap_filtre')));
        if ($filtre === '') {
            return false;
        }

        return Str::contains(Str::lower($email['sujet'].' '.$email['expediteur'].' '.$email['expediteur_email'].' '.Str::substr($email['texte'], -400)), $filtre);
    }

    /**
     * Lit les champs habituels des formulaires WordPress (Contact Form 7, WPForms, Elementor…).
     *
     * @return array{nom: ?string, telephone: ?string, email: ?string, adresse: ?string, code_postal: ?string, ville: ?string, message: string}
     */
    public static function analyser(string $texte): array
    {
        $texte = str_replace(["\r\n", "\r"], "\n", $texte);
        // « Libellé : valeur » ou libellé seul sur sa ligne et valeur dessous (WPForms).
        $champ = function (array $libelles) use ($texte): ?string {
            $motif = '/^[ \t]*(?:'.implode('|', $libelles).')[ \t]*\*?[ \t]*(?::[ \t]*(\S.*)|\n[ \t]*(\S.*))$/imu';

            return preg_match($motif, $texte, $m) ? Str::limit(trim(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '')), 150, '') : null;
        };

        $email = $champ(['e-?mail', 'courriel', 'adresse e-?mail', 'votre e-?mail']);
        if (! $email && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $texte, $m)) {
            $email = $m[0];
        }
        if ($email && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $email, $m)) {
            $email = mb_strtolower($m[0]);
        } else {
            $email = null;
        }

        $telephone = $champ(['t[ée]l[ée]phone', 't[ée]l\.?', 'portable', 'phone', 'mobile']);
        $message = null;
        if (preg_match('/^[ \t]*(?:message|votre message|description|projet|travaux|vos travaux|nature des travaux)[ \t]*\*?[ \t]*(?::|\n)\s*(.*)$/imsu', $texte, $m)) {
            $message = trim(preg_split('/\n\s*(?:--|—)\s*\n/u', $m[1])[0]);
        }

        return [
            'nom' => $champ(['nom(?: et pr[ée]nom)?', 'votre nom', 'name', 'pr[ée]nom et nom']),
            'telephone' => $telephone ? Telephone::normaliser($telephone) : null,
            'email' => $email,
            'adresse' => $champ(['adresse(?! ?(?:e-?mail|mail|électronique))(?: des travaux| du chantier)?']),
            'code_postal' => ($cp = $champ(['code postal', 'cp'])) && preg_match('/\b(\d{5})\b/', $cp, $c) ? $c[1] : null,
            'ville' => $champ(['ville', 'commune', 'localit[ée]']),
            'message' => Str::limit($message ?: trim($texte), 5000),
        ];
    }

    public static function alerter(int $nombre): void
    {
        Alertes::envoyer(User::gerantsActifs()->get(), new AlerteDocument(
            $nombre > 1 ? $nombre.' nouvelles demandes de devis' : 'Nouvelle demande de devis',
            'Une personne a demandé un devis depuis votre site. Rappelez-la vite !',
            route('suivi'),
        ));
    }
}
