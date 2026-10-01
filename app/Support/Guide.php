<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Guide intégré : une rubrique par fonctionnalité.
 * Chaque rubrique indique la page concernée ; les tests vérifient que ces pages existent
 * et que le commercial ne voit pas les rubriques des pages qu'il ne peut pas ouvrir.
 */
class Guide
{
    /**
     * @return array<string, array{titre: string, route: string, pages: list<string>, gerant: bool, etapes: list<string>, bon_a_savoir: string, capture: ?string}>
     */
    public static function rubriques(): array
    {
        return [
            'accueil' => [
                'titre' => 'L\'accueil',
                'route' => 'accueil', 'pages' => ['accueil*'], 'gerant' => false, 'capture' => 'accueil',
                'etapes' => [
                    'Le bloc « Aujourd\'hui » montre les rendez-vous et chantiers du jour, avec la météo, l\'itinéraire et le bouton Appeler.',
                    '« Appels à passer » liste les nouvelles demandes et les devis sans réponse depuis 7 jours.',
                    'Touchez « Personnaliser l\'accueil et la barre du bas » pour choisir les blocs, leur ordre et les deux boutons du milieu de la barre du bas.',
                ],
                'bon_a_savoir' => 'Chaque compte a son propre accueil. Le commercial ne voit jamais le chiffre d\'affaires.',
            ],
            'clients' => [
                'titre' => 'Clients et chantiers',
                'route' => 'clients.index', 'pages' => ['clients.*', 'chantiers.*', 'notes.*', 'pieces.*'], 'gerant' => false, 'capture' => 'clients',
                'etapes' => [
                    'Touchez Nouveau → Un client. Indiquez au moins le nom et un téléphone ou un email.',
                    'Sur la fiche, ajoutez une ou plusieurs adresses de chantier (type de toiture, surface, pente, accès).',
                    'Ajoutez des notes et des fichiers (photos, PDF) sur la fiche.',
                    'Pour reprendre votre ancien fichier, touchez « Importer » dans la liste des clients (fichier CSV, par exemple enregistré depuis Excel).',
                ],
                'bon_a_savoir' => 'L\'application vous prévient si un client semble déjà exister (même téléphone ou même email).',
            ],
            'devis' => [
                'titre' => 'Faire un devis',
                'route' => 'devis.create', 'pages' => ['devis.index', 'devis.create', 'devis.edit', 'devis.show', 'devis.store', 'catalogue.*'], 'gerant' => false, 'capture' => 'devis',
                'etapes' => [
                    'Touchez Nouveau → Un devis et choisissez le client.',
                    'Ajoutez des lignes à la main ou depuis le catalogue ; ajoutez des sections et des options si besoin.',
                    'Les totaux se calculent tout seuls. Enregistrez : le devis reste en brouillon tant qu\'il n\'est pas envoyé.',
                    'Touchez « Envoyer » dans la barre du bas : le devis reçoit son numéro et son PDF est figé.',
                ],
                'bon_a_savoir' => 'Un devis envoyé ne se modifie plus : faites une nouvelle version. Les prix se saisissent hors taxes.',
            ],
            'devis-express' => [
                'titre' => 'Le devis express (une phrase)',
                'route' => 'devis.express', 'pages' => ['devis.express*'], 'gerant' => false, 'capture' => 'devis-express',
                'etapes' => [
                    'Touchez Nouveau → Un devis express.',
                    'Écrivez ou dictez une phrase, par exemple : “Mme Martin, démoussage 120 m² à 12 €, échafaudage forfait 600 €”.',
                    'Vérifiez l\'aperçu, puis créez le brouillon.',
                ],
                'bon_a_savoir' => 'Le client doit déjà exister. Rien n\'est envoyé : vous relisez le brouillon avant de l\'envoyer.',
            ],
            'signature' => [
                'titre' => 'Faire signer un devis',
                'route' => 'devis.index', 'pages' => ['devis.signer'], 'gerant' => false, 'capture' => null,
                'etapes' => [
                    'Sur place : ouvrez le devis envoyé et touchez « Faire signer ». Le client signe avec le doigt.',
                    'À distance : le client ouvre son lien personnel, lit le devis et signe en ligne.',
                    'Vous recevez une alerte dès que le devis est signé, refusé ou qu\'une modification est demandée.',
                ],
                'bon_a_savoir' => 'Pour un devis signé hors de vos locaux, le client a 14 jours pour se rétracter.',
            ],
            'partage' => [
                'titre' => 'Envoyer par email, SMS ou WhatsApp',
                'route' => 'devis.index', 'pages' => ['envoi.*'], 'gerant' => false, 'capture' => null,
                'etapes' => [
                    'Sur un devis ou une facture, touchez « Envoyer » : le message est déjà rédigé d\'après vos modèles.',
                    'Vous pouvez aussi copier le message prêt ou l\'envoyer par SMS ou WhatsApp avec le lien du client.',
                    'Chaque email envoyé est noté sur le document.',
                ],
                'bon_a_savoir' => 'Les emails partent de votre adresse Gmail, réglée dans Réglages → Emails.',
            ],
            'factures' => [
                'titre' => 'Factures et avoirs',
                'route' => 'factures.index', 'pages' => ['factures.*'], 'gerant' => true, 'capture' => 'factures',
                'etapes' => [
                    'Depuis un devis accepté, touchez « Facturer » : facture complète, acompte, situation ou solde.',
                    'Vérifiez le brouillon puis touchez « Émettre » : la facture reçoit son numéro et ne se modifie plus.',
                    'Pour corriger une facture émise, faites un avoir (total ou partiel).',
                    'Dans « Frais du chantier », notez vos achats avec la photo du ticket pour voir ce qu\'il vous reste.',
                ],
                'bon_a_savoir' => 'Une facture émise n\'est jamais supprimée : elle est conservée 10 ans.',
            ],
            'paiements' => [
                'titre' => 'Encaisser un paiement',
                'route' => 'factures.index', 'pages' => ['paiements.*'], 'gerant' => true, 'capture' => null,
                'etapes' => [
                    'Sur la facture, touchez « Encaisser », indiquez le montant, la date et le mode de paiement.',
                    'Le client peut aussi payer par carte avec son lien, si le paiement en ligne est activé.',
                    'Les relances automatiques partent après l\'échéance si vous cochez l\'option sur la facture.',
                ],
                'bon_a_savoir' => 'Un encaissement ne s\'efface pas : une annulation ajoute une ligne inverse.',
            ],
            'planning' => [
                'titre' => 'Planning et rappels',
                'route' => 'planning.index', 'pages' => ['planning.*'], 'gerant' => false, 'capture' => 'planning',
                'etapes' => [
                    'Touchez Nouveau → Un rendez-vous. Un chantier peut durer plusieurs jours.',
                    'Les devis acceptés sans date sont dans Planning → À planifier.',
                    'Vous recevez un rappel 1 heure avant et la veille à 19 h si les notifications sont activées. Le client peut recevoir un email 1 ou 2 jours avant.',
                    'Touchez « Ajouter à mon agenda » pour mettre le rendez-vous dans l\'agenda du téléphone.',
                ],
                'bon_a_savoir' => 'La météo (pluie, vent fort, gel) est mise à jour chaque heure pour les adresses des 8 prochains jours.',
            ],
            'photos' => [
                'titre' => 'Photos et rapports d\'intervention',
                'route' => 'clients.index', 'pages' => ['photos.*', 'rapports.*'], 'gerant' => false, 'capture' => 'photos',
                'etapes' => [
                    'Sur la fiche client ou un rendez-vous, ajoutez des photos : avant, pendant, après, problème, réparation.',
                    'Touchez « Dessiner sur la photo » pour entourer ou flécher un défaut.',
                    'Cochez « Mettre dans les devis et factures » pour les joindre en annexe des PDF.',
                    'Faites le rapport d\'intervention (constat, travaux, préconisations, photos) et envoyez-le au client.',
                ],
                'bon_a_savoir' => 'Les photos sont réduites et la position GPS du téléphone est retirée.',
            ],
            'suivi' => [
                'titre' => 'Suivi commercial',
                'route' => 'suivi', 'pages' => ['suivi*'], 'gerant' => false, 'capture' => 'suivi',
                'etapes' => [
                    'Les demandes du formulaire de votre site arrivent dans « Nouvelles demandes » : touchez « Créer le client ».',
                    'Les devis sans réponse sont relancés automatiquement à 7 et 15 jours.',
                    'Demandez un avis Google aux clients satisfaits et proposez l\'entretien aux anciens clients.',
                ],
                'bon_a_savoir' => 'La lecture des emails du site se fait en lecture seule : rien n\'est supprimé ni marqué comme lu.',
            ],
            'statistiques' => [
                'titre' => 'Statistiques',
                'route' => 'statistiques', 'pages' => ['statistiques*'], 'gerant' => true, 'capture' => 'statistiques',
                'etapes' => [
                    'Plus → Statistiques : facturé par mois, par provenance des clients et par compte.',
                    'Statistiques → Site internet : visites, provenance et boutons cliqués sur votre site.',
                ],
                'bon_a_savoir' => 'Le compteur du site n\'utilise aucun cookie et n\'enregistre aucune adresse IP.',
            ],
            'comptes' => [
                'titre' => 'Comptes',
                'route' => 'comptes', 'pages' => ['comptes*'], 'gerant' => true, 'capture' => null,
                'etapes' => [
                    'Plus → Comptes → Inviter une personne : elle reçoit un lien pour choisir son mot de passe.',
                    'Un commercial voit les clients, devis, rendez-vous, photos et rapports, mais jamais les factures ni les chiffres.',
                    'Touchez « Désactiver » : la personne est déconnectée tout de suite.',
                ],
                'bon_a_savoir' => 'Le lien d\'invitation est valable 7 jours.',
            ],
            'reglages' => [
                'titre' => 'Réglages',
                'route' => 'reglages', 'pages' => ['reglages*', 'configuration*'], 'gerant' => true, 'capture' => null,
                'etapes' => [
                    'Entreprise : coordonnées, SIRET, médiateur. Apparence : logo et couleurs.',
                    'Documents : IBAN, CGV, validité des devis. Assurance : attestation décennale et date de fin.',
                    'Emails : adresse Gmail et mot de passe d\'application. Modèles d\'emails : vos textes.',
                ],
                'bon_a_savoir' => 'Les mots de passe et clés sont enregistrés chiffrés et ne sont jamais réaffichés.',
            ],
            'claude' => [
                'titre' => 'Préparer des devis avec Claude',
                'route' => 'reglages.claude', 'pages' => ['reglages.claude*'], 'gerant' => true, 'capture' => null,
                'etapes' => [
                    'Réglages → Accès Claude : créez une clé et copiez-la (elle n\'est affichée qu\'une fois).',
                    'Dans les réglages de l\'environnement de Claude, ajoutez MC_API_URL et MC_API_TOKEN.',
                    'Demandez à Claude de préparer un devis : il crée un brouillon que vous relisez avant l\'envoi.',
                ],
                'bon_a_savoir' => 'Ne collez jamais la clé dans une conversation. Vous pouvez la révoquer à tout moment.',
            ],
            'telephone' => [
                'titre' => 'Sur le téléphone',
                'route' => 'plus', 'pages' => ['plus', 'nouveau'], 'gerant' => false, 'capture' => null,
                'etapes' => [
                    'Ajoutez l\'application à l\'écran d\'accueil (Partager → Sur l\'écran d\'accueil sur iPhone, menu ⋮ → Installer sur Android).',
                    'Plus → Notifications : activez-les pour être prévenu d\'un devis signé, d\'un paiement ou d\'un rendez-vous.',
                    'Plus → Affichage : thème clair ou sombre, grands boutons.',
                    'Sans réseau, les dernières pages ouvertes restent lisibles ; l\'application réessaie quand la connexion revient.',
                ],
                'bon_a_savoir' => 'Le bouton « ? » en haut de chaque page ouvre l\'aide de la page.',
            ],
        ];
    }

    /**
     * Rubriques visibles par ce compte (le commercial ne voit pas les pages du gérant).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function pour(User $user): array
    {
        return array_filter(self::rubriques(), fn (array $r) => ! $r['gerant'] || $user->estGerant());
    }

    /**
     * Rubrique de la page en cours (bouton « ? »).
     */
    public static function rubriqueDeLaPage(): ?string
    {
        $route = Route::currentRouteName();
        if (! $route) {
            return null;
        }
        // Les motifs les plus précis d'abord (ex. « reglages.claude* » avant « reglages* »).
        $motifs = [];
        foreach (self::rubriques() as $cle => $r) {
            foreach ($r['pages'] as $motif) {
                $motifs[] = [$motif, $cle];
            }
        }
        usort($motifs, fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
        foreach ($motifs as [$motif, $cle]) {
            if (Str::is($motif, $route)) {
                return $cle;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rubriques
     * @return array<string, array<string, mixed>>
     */
    public static function chercher(array $rubriques, string $recherche): array
    {
        $mots = array_filter(explode(' ', Texte::pourRecherche($recherche)));
        if (! $mots) {
            return $rubriques;
        }

        return array_filter($rubriques, function (array $r) use ($mots) {
            $texte = Texte::pourRecherche($r['titre'].' '.implode(' ', $r['etapes']).' '.$r['bon_a_savoir']);
            foreach ($mots as $mot) {
                if (! str_contains($texte, $mot)) {
                    return false;
                }
            }

            return true;
        });
    }
}
