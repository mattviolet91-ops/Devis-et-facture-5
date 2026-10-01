<?php

namespace App\Support;

use App\Rules\Bic;
use App\Rules\Iban;
use App\Rules\Siret;
use App\Rules\TvaIntracom;
use App\Services\MyPos;
use App\Services\Numerotation;

/**
 * Description des écrans de Réglages : chaque champ indique sa clé de réglage,
 * son libellé, son type et ses règles. Le contrôleur et la vue sont génériques.
 *
 * Types : texte, email, tel, url, textarea, entier, select, case, couleur, date,
 * fichier, secret, taux (liste de taux de TVA), lignes (une valeur par ligne).
 */
class SectionsReglages
{
    public const FORMES_JURIDIQUES = [
        'EI' => 'Entreprise individuelle (EI)',
        'Micro-entreprise' => 'Micro-entreprise (EI)',
        'EURL' => 'EURL',
        'SARL' => 'SARL',
        'SASU' => 'SASU',
        'SAS' => 'SAS',
        'SA' => 'SA',
        'Autre' => 'Autre',
    ];

    public const POLICES = [
        'systeme' => 'Moderne (police du téléphone)',
        'arrondie' => 'Arrondie',
        'classique' => 'Classique (avec empattements)',
    ];

    /**
     * @return array<string, array{titre: string, description: string, icone: string, champs: list<array<string, mixed>>}>
     */
    public static function toutes(): array
    {
        return [
            'entreprise' => [
                'titre' => 'Entreprise',
                'description' => 'Nom, adresse, SIRET : ce qui apparaît sur vos devis et factures.',
                'icone' => 'accueil',
                'champs' => [
                    ['cle' => 'identite.nom_commercial', 'libelle' => 'Nom commercial', 'type' => 'texte', 'obligatoire' => true, 'regles' => ['max:120'], 'autocomplete' => 'organization'],
                    ['cle' => 'identite.forme_juridique', 'libelle' => 'Forme juridique', 'type' => 'select', 'options' => self::FORMES_JURIDIQUES],
                    ['cle' => 'identite.capital', 'libelle' => 'Capital social', 'type' => 'texte', 'regles' => ['max:40'], 'aide' => 'Pour une société seulement, par exemple « 5 000 € ».'],
                    ['cle' => 'identite.adresse', 'libelle' => 'Adresse', 'type' => 'texte', 'obligatoire' => true, 'regles' => ['max:200'], 'autocomplete' => 'street-address'],
                    ['cle' => 'identite.code_postal', 'libelle' => 'Code postal', 'type' => 'texte', 'obligatoire' => true, 'regles' => ['regex:/^\d{5}$/'], 'inputmode' => 'numeric', 'autocomplete' => 'postal-code', 'message' => 'Le code postal fait 5 chiffres.'],
                    ['cle' => 'identite.ville', 'libelle' => 'Ville', 'type' => 'texte', 'obligatoire' => true, 'regles' => ['max:100'], 'autocomplete' => 'address-level2'],
                    ['cle' => 'identite.telephone', 'libelle' => 'Téléphone', 'type' => 'tel', 'obligatoire' => true, 'regles' => ['regex:/^[0-9 +().\-]{10,20}$/'], 'message' => 'Indiquez un numéro de téléphone complet (10 chiffres).'],
                    ['cle' => 'identite.email', 'libelle' => 'Email de l\'entreprise', 'type' => 'email', 'obligatoire' => true, 'regles' => ['email', 'max:150']],
                    ['cle' => 'identite.site', 'libelle' => 'Site internet', 'type' => 'url', 'regles' => ['url:https,http', 'max:200'], 'aide' => 'Commence par https://'],
                    ['cle' => 'identite.siret', 'libelle' => 'SIRET', 'type' => 'texte', 'obligatoire' => true, 'regles' => [new Siret], 'inputmode' => 'numeric', 'aide' => '14 chiffres, sur votre Kbis ou avis de situation INSEE.'],
                    ['cle' => 'identite.code_ape', 'libelle' => 'Code APE', 'type' => 'texte', 'regles' => ['regex:/^\d{2}\.?\d{2}[A-Za-z]$/'], 'message' => 'Le code APE s\'écrit 4 chiffres et une lettre, par exemple 4391B.', 'aide' => 'Exemple : 4391B (travaux de couverture).'],
                    ['cle' => 'identite.rcs_rm', 'libelle' => 'Immatriculation (RCS ou RNE)', 'type' => 'texte', 'regles' => ['max:120'], 'aide' => 'Par exemple « RCS Ville » ou « Inscrit au RNE ».'],
                    ['cle' => 'identite.agrements', 'libelle' => 'Qualifications et agréments', 'type' => 'textarea', 'regles' => ['max:1000'], 'aide' => 'Par exemple RGE, Qualibat… (un par ligne).'],
                    ['cle' => 'identite.mediateur_nom', 'libelle' => 'Médiateur de la consommation', 'type' => 'texte', 'regles' => ['max:200'], 'aide' => 'Obligatoire pour travailler avec des particuliers. Nom et adresse du médiateur auquel vous adhérez.'],
                    ['cle' => 'identite.mediateur_site', 'libelle' => 'Site du médiateur', 'type' => 'url', 'regles' => ['url:https,http', 'max:200']],
                ],
            ],

            'apparence' => [
                'titre' => 'Apparence',
                'description' => 'Logo, icône et couleurs de l\'application et des documents.',
                'icone' => 'reglages',
                'champs' => [
                    ['cle' => 'apparence.logo', 'libelle' => 'Logo', 'type' => 'fichier', 'regles' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], 'accept' => 'image/png,image/jpeg,image/webp', 'aide' => 'Image PNG ou JPG, 2 Mo maximum. Il apparaît sur vos devis et factures.'],
                    ['cle' => 'apparence.icone', 'libelle' => 'Icône de l\'application', 'type' => 'fichier', 'regles' => ['image', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:min_width=192,min_height=192,ratio=1'], 'accept' => 'image/png,image/jpeg', 'aide' => 'Image carrée, au moins 512 × 512 pixels. Elle s\'affiche sur l\'écran du téléphone.'],
                    ['cle' => 'apparence.couleur_principale', 'libelle' => 'Couleur principale', 'type' => 'couleur', 'obligatoire' => true],
                    ['cle' => 'apparence.couleur_accent', 'libelle' => 'Couleur du bouton « Nouveau »', 'type' => 'couleur', 'obligatoire' => true],
                    ['cle' => 'apparence.police', 'libelle' => 'Police', 'type' => 'select', 'obligatoire' => true, 'options' => self::POLICES],
                ],
            ],

            'tva' => [
                'titre' => 'TVA',
                'description' => 'Régime de TVA, taux et unités.',
                'icone' => 'factures',
                'champs' => [
                    ['cle' => 'tva.regime', 'libelle' => 'Régime de TVA', 'type' => 'select', 'obligatoire' => true, 'options' => [
                        'franchise' => 'Franchise en base (pas de TVA)',
                        'assujetti' => 'Assujetti à la TVA',
                    ], 'aide' => 'En franchise, vos documents portent la mention « TVA non applicable, art. 293 B du CGI ».'],
                    ['cle' => 'identite.tva_intracom', 'libelle' => 'Numéro de TVA intracommunautaire', 'type' => 'texte', 'regles' => ['required_if:tva__regime,assujetti'], 'regle_tva' => true, 'aide' => 'Obligatoire si vous êtes assujetti. Exemple : FR 12 345678901.', 'message_requis' => 'Assujetti à la TVA : le numéro de TVA intracommunautaire est obligatoire.'],
                    ['cle' => 'tva.taux', 'libelle' => 'Taux utilisés', 'type' => 'taux', 'obligatoire' => true, 'aide' => 'Séparés par un point-virgule, par exemple : 20 ; 10 ; 5,5 ; 0'],
                    ['cle' => 'tva.taux_defaut', 'libelle' => 'Taux proposé par défaut', 'type' => 'taux_defaut', 'obligatoire' => true, 'aide' => 'Travaux de rénovation d\'un logement de plus de 2 ans : souvent 10 %. Faites confirmer par votre comptable.'],
                    ['cle' => 'tva.unites', 'libelle' => 'Unités', 'type' => 'lignes', 'obligatoire' => true, 'aide' => 'Une unité par ligne (m², ml, h, forfait…).'],
                ],
            ],

            'numerotation' => [
                'titre' => 'Numérotation',
                'description' => 'Numéros des devis, factures et avoirs : continus et sans trou.',
                'icone' => 'journal',
                'champs' => self::champsNumerotation(),
            ],

            'documents' => [
                'titre' => 'Documents',
                'description' => 'Validité, paiement, IBAN, mention sur les déchets, CGV.',
                'icone' => 'devis',
                'champs' => [
                    ['cle' => 'documents.validite_devis_jours', 'libelle' => 'Validité des devis', 'type' => 'entier', 'obligatoire' => true, 'regles' => ['min:1', 'max:365'], 'suffixe' => 'jours'],
                    ['cle' => 'documents.delai_paiement_jours', 'libelle' => 'Délai de paiement des factures', 'type' => 'entier', 'obligatoire' => true, 'regles' => ['min:0', 'max:60'], 'suffixe' => 'jours', 'aide' => 'Entre professionnels, la loi limite le délai à 60 jours.'],
                    ['cle' => 'documents.acompte_pourcentage', 'libelle' => 'Acompte demandé', 'type' => 'entier', 'obligatoire' => true, 'regles' => ['min:0', 'max:100'], 'suffixe' => '%'],
                    ['cle' => 'documents.iban', 'libelle' => 'IBAN', 'type' => 'texte', 'regles' => [new Iban], 'aide' => 'Pour les virements, imprimé sur vos factures.'],
                    ['cle' => 'documents.bic', 'libelle' => 'BIC', 'type' => 'texte', 'regles' => ['required_with:documents__iban', new Bic], 'message_requis' => 'Avec un IBAN, indiquez aussi le BIC.'],
                    ['cle' => 'documents.mention_dechets', 'libelle' => 'Mention sur les déchets', 'type' => 'textarea', 'regles' => ['max:2000'], 'aide' => 'Obligatoire sur les devis de travaux : tri, collecte et coût d\'évacuation des déchets.'],
                    ['cle' => 'documents.cgv', 'libelle' => 'Conditions générales de vente (CGV)', 'type' => 'textarea', 'grand' => true, 'regles' => ['max:30000'], 'aide' => 'Ajoutées en annexe de vos devis.'],
                    ['cle' => 'documents.page_couverture', 'libelle' => 'Ajouter une page de couverture aux devis', 'type' => 'case'],
                ],
            ],

            'assurance' => [
                'titre' => 'Assurance décennale',
                'description' => 'Contrat, dates de validité et attestation.',
                'icone' => 'cloche',
                'champs' => [
                    ['cle' => 'assurance.assureur', 'libelle' => 'Assureur', 'type' => 'texte', 'regles' => ['max:150']],
                    ['cle' => 'assurance.numero_contrat', 'libelle' => 'Numéro de contrat', 'type' => 'texte', 'regles' => ['max:80']],
                    ['cle' => 'assurance.date_debut', 'libelle' => 'Valable du', 'type' => 'date', 'regles' => ['date']],
                    ['cle' => 'assurance.date_fin', 'libelle' => 'Valable jusqu\'au', 'type' => 'date', 'regles' => ['date'], 'apres' => 'assurance__date_debut', 'message' => 'La date de fin doit être après la date de début.'],
                    ['cle' => 'assurance.activites', 'libelle' => 'Activités couvertes', 'type' => 'textarea', 'regles' => ['max:2000'], 'aide' => 'Recopiez les activités de votre attestation.'],
                    ['cle' => 'assurance.zone', 'libelle' => 'Zone couverte', 'type' => 'texte', 'regles' => ['max:150'], 'aide' => 'Par exemple « France métropolitaine ».'],
                    ['cle' => 'assurance.attestation', 'libelle' => 'Attestation (PDF)', 'type' => 'fichier', 'regles' => ['mimes:pdf', 'max:5120'], 'accept' => 'application/pdf', 'aide' => 'PDF, 5 Mo maximum. Elle peut être jointe en annexe des devis.'],
                    ['cle' => 'assurance.alerte_jours', 'libelle' => 'M\'alerter avant la fin du contrat', 'type' => 'entier', 'obligatoire' => true, 'regles' => ['min:7', 'max:120'], 'suffixe' => 'jours avant'],
                ],
            ],

            'emails' => [
                'titre' => 'Emails',
                'description' => 'Compte Gmail utilisé pour envoyer devis et factures.',
                'icone' => 'cloche',
                'champs' => [
                    ['cle' => 'emails.adresse', 'libelle' => 'Adresse Gmail', 'type' => 'email', 'regles' => ['email', 'max:150'], 'aide' => 'Les emails partent de cette adresse.'],
                    ['cle' => 'emails.nom_expediteur', 'libelle' => 'Nom affiché', 'type' => 'texte', 'regles' => ['max:120'], 'aide' => 'Laissez vide pour utiliser le nom commercial.'],
                    ['cle' => 'emails.mot_de_passe', 'libelle' => 'Mot de passe d\'application Google', 'type' => 'secret', 'regles' => ['max:64'], 'aide' => 'À créer dans votre compte Google : Sécurité → Validation en deux étapes → Mots de passe des applications (16 lettres). Ce n\'est pas votre mot de passe Gmail habituel.'],
                    ['cle' => 'emails.copie_cachee', 'libelle' => 'Recevoir une copie cachée de chaque envoi', 'type' => 'case'],
                ],
            ],

            'clients' => [
                'titre' => 'Clients',
                'description' => 'Liste « Comment nous a-t-il connus ? ».',
                'icone' => 'clients',
                'champs' => [
                    ['cle' => 'clients.provenances', 'libelle' => 'Provenances proposées', 'type' => 'lignes', 'obligatoire' => true, 'aide' => 'Une par ligne. Elles servent aux statistiques par provenance.'],
                ],
            ],

            'suivi' => [
                'titre' => 'Suivi commercial',
                'description' => 'Relances de devis, avis Google, entretien, demandes du site internet.',
                'icone' => 'cloche',
                'champs' => [
                    ['cle' => 'suivi.relances_devis', 'libelle' => 'Relancer automatiquement les devis sans réponse (à 7 et 15 jours)', 'type' => 'case'],
                    ['cle' => 'suivi.lien_avis', 'libelle' => 'Lien pour laisser un avis Google', 'type' => 'url', 'regles' => ['url:https', 'max:500'], 'aide' => 'Dans votre fiche Google (Google Business Profile) : « Demander des avis » → copier le lien.'],
                    ['cle' => 'suivi.entretien_mois', 'libelle' => 'Proposer un entretien après (mois)', 'type' => 'entier', 'obligatoire' => true, 'regles' => ['integer', 'min:1', 'max:60']],
                    ['cle' => 'suivi.formulaire_actif', 'libelle' => 'Activer le formulaire de demande de devis (à mettre en lien sur votre site)', 'type' => 'case'],
                    ['cle' => 'suivi.imap_actif', 'libelle' => 'Lire les emails reçus (demandes envoyées par le site WordPress)', 'type' => 'case', 'aide' => 'Utilise l\'adresse Gmail et le mot de passe d\'application de la rubrique Emails. Les emails ne sont jamais supprimés ni modifiés.'],
                    ['cle' => 'suivi.imap_filtre', 'libelle' => 'Mot qui repère un email du site', 'type' => 'texte', 'regles' => ['max:100'], 'aide' => 'Cherché dans l\'expéditeur ou l\'objet. Par exemple : WordPress, Formulaire de contact.'],
                ],
            ],

            'paiement' => [
                'titre' => 'Paiement en ligne',
                'description' => 'Paiement par carte avec myPOS Checkout.',
                'icone' => 'factures',
                'champs' => [
                    ['cle' => 'mypos.mode', 'libelle' => 'Paiement par carte', 'type' => 'select', 'obligatoire' => true, 'options' => [
                        'desactive' => 'Désactivé',
                        'test' => 'Mode test (aucun argent réel, invisible pour les clients)',
                        'production' => 'Activé pour les clients',
                    ]],
                    ['cle' => 'mypos.pack', 'libelle' => 'Pack de configuration myPOS', 'type' => 'secret', 'regles' => ['max:20000', function ($attribut, $valeur, $echec) {
                        if (is_string($valeur) && trim($valeur) !== '' && MyPos::lirePack($valeur) === null) {
                            $echec('Ce pack de configuration n\'est pas valable. Copiez-le en entier depuis votre compte myPOS.');
                        }
                    }], 'aide' => 'À copier depuis votre compte myPOS (paramètres de la boutique en ligne). Il est enregistré chiffré et ne sera plus jamais affiché.'],
                ],
            ],

            'modeles' => [
                'titre' => 'Modèles d\'emails',
                'description' => 'Textes proposés pour l\'envoi des devis, factures, relances et rappels de rendez-vous.',
                'icone' => 'devis',
                'champs' => self::champsModeles(),
            ],
        ];
    }

    /**
     * @return array{titre: string, description: string, icone: string, champs: list<array<string, mixed>>}|null
     */
    public static function section(string $cle): ?array
    {
        return self::toutes()[$cle] ?? null;
    }

    public static function nomChamp(string $cle): string
    {
        return str_replace('.', '__', $cle);
    }

    /**
     * Règles de validation Laravel d'une section.
     *
     * @return array<string, mixed>
     */
    public static function regles(array $section, array $donnees): array
    {
        $regles = [];

        foreach ($section['champs'] as $champ) {
            if (! empty($champ['verrouille'])) {
                continue;
            }

            $nom = self::nomChamp($champ['cle']);
            $base = match ($champ['type']) {
                'case' => ['boolean'],
                'entier' => ['integer'],
                'couleur' => ['regex:/^#[0-9a-fA-F]{6}$/'],
                'select' => ['in:'.implode(',', array_keys($champ['options']))],
                'fichier' => ['file'],
                'taux', 'lignes', 'taux_defaut' => ['string', 'max:500'],
                default => ['string'],
            };

            if (! empty($champ['apres']) && ! empty($donnees[$champ['apres']])) {
                $base[] = 'after:'.$champ['apres'];
            }

            if (! empty($champ['regle_tva'])) {
                $base[] = new TvaIntracom($donnees['identite__siret'] ?? reglage('identite.siret'));
            }

            $presence = match (true) {
                $champ['type'] === 'case', $champ['type'] === 'fichier', $champ['type'] === 'secret' => ['nullable'],
                ! empty($champ['obligatoire']) => ['required'],
                default => ['nullable'],
            };

            $regles[$nom] = [...$presence, ...$base, ...($champ['regles'] ?? [])];
        }

        return $regles;
    }

    /**
     * Messages personnalisés d'une section.
     *
     * @return array<string, string>
     */
    public static function messages(array $section): array
    {
        $messages = [];

        foreach ($section['champs'] as $champ) {
            $nom = self::nomChamp($champ['cle']);
            if (isset($champ['message'])) {
                $messages[$nom.'.regex'] = $champ['message'];
                $messages[$nom.'.after'] = $champ['message'];
            }
            if (isset($champ['message_requis'])) {
                $messages[$nom.'.required_if'] = $champ['message_requis'];
                $messages[$nom.'.required_with'] = $champ['message_requis'];
            }
            if ($champ['type'] === 'couleur') {
                $messages[$nom.'.regex'] = 'Choisissez une couleur.';
            }
            if ($champ['type'] === 'fichier') {
                $messages[$nom.'.dimensions'] = 'L\'image doit être carrée et faire au moins 192 × 192 pixels (512 × 512 conseillé).';
            }
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public static function libelles(array $section): array
    {
        return collect($section['champs'])
            ->mapWithKeys(fn (array $c) => [self::nomChamp($c['cle']) => $c['libelle']])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function champsNumerotation(): array
    {
        $numerotation = app(Numerotation::class);
        $champs = [];

        foreach (['devis' => 'devis', 'facture' => 'factures', 'avoir' => 'avoirs'] as $type => $pluriel) {
            $verrouille = ! $numerotation->premierModifiable($type);
            $aide = 'Prochain numéro : '.$numerotation->prochain($type).'.';
            if ($verrouille) {
                $aide .= ' Des numéros ont déjà été attribués cette année : ce réglage ne peut plus changer (sinon il y aurait un trou ou un doublon).';
            }

            $champs[] = ['cle' => "numerotation.{$type}_prefixe", 'libelle' => "Préfixe des {$pluriel}", 'type' => 'texte', 'obligatoire' => true, 'regles' => ['regex:/^[A-Z]{1,6}$/'], 'message' => 'Le préfixe s\'écrit en 1 à 6 lettres majuscules, sans accent (exemple : DEV).', 'verrouille' => $verrouille, 'aide' => $aide];
            $champs[] = ['cle' => "numerotation.{$type}_premier", 'libelle' => "Premier numéro des {$pluriel}", 'type' => 'entier', 'obligatoire' => true, 'regles' => ['min:1', 'max:999999'], 'verrouille' => $verrouille, 'aide' => 'Pour continuer une numérotation existante : mettez le numéro qui suit votre dernier document.'];
        }

        return $champs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function champsModeles(): array
    {
        $variables = 'Variables possibles : {salutation}, {numero}, {montant}, {echeance}, {lien}, {entreprise}.';
        $champs = [];

        foreach (['devis' => 'Envoi d\'un devis', 'relance_devis' => 'Relance d\'un devis', 'facture' => 'Envoi d\'une facture', 'relance' => 'Relance d\'une facture', 'rapport' => 'Rapport d\'intervention', 'avis' => 'Demande d\'avis', 'entretien' => 'Proposition d\'entretien'] as $cle => $titre) {
            $champs[] = ['cle' => "emails.modeles.{$cle}.sujet", 'libelle' => "{$titre} : objet", 'type' => 'texte', 'obligatoire' => true, 'regles' => ['max:200']];
            $champs[] = ['cle' => "emails.modeles.{$cle}.corps", 'libelle' => "{$titre} : message", 'type' => 'textarea', 'obligatoire' => true, 'regles' => ['max:5000'], 'aide' => $variables];
        }

        $champs[] = ['cle' => 'emails.modeles.rendez_vous.sujet', 'libelle' => 'Rappel de rendez-vous au client : objet', 'type' => 'texte', 'obligatoire' => true, 'regles' => ['max:200']];
        $champs[] = ['cle' => 'emails.modeles.rendez_vous.corps', 'libelle' => 'Rappel de rendez-vous au client : message', 'type' => 'textarea', 'obligatoire' => true, 'regles' => ['max:5000'],
            'aide' => 'Variables possibles : {salutation}, {date}, {horaire}, {objet}, {adresse}, {telephone}, {entreprise}.'];

        return $champs;
    }
}
