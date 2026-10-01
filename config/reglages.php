<?php

/*
|--------------------------------------------------------------------------
| Valeurs par défaut des réglages qui ne sont PAS des données d'entreprise
|--------------------------------------------------------------------------
|
| Conventions de l'application (préfixes, taux légaux, délais usuels…).
| Les informations propres à l'entreprise restent dans config/entreprise.php,
| où elles sont toujours vides.
|
*/

return [
    'apparence' => [
        'couleur_principale' => '#1f4e79',
        'couleur_accent' => '#c25e00',
        'police' => 'systeme',
    ],

    'tva' => [
        'regime' => 'assujetti',
        // Taux en centièmes de pour cent (2000 = 20 %) : jamais de nombre à virgule.
        'taux' => [2000, 1000, 550, 0],
        'taux_defaut' => 1000,
        'unites' => ['u', 'm²', 'ml', 'm³', 'h', 'jour', 'forfait', 'kg', 't'],
    ],

    'numerotation' => [
        'devis_prefixe' => 'DEV',
        'devis_premier' => 1,
        'facture_prefixe' => 'FAC',
        'facture_premier' => 1,
        'avoir_prefixe' => 'AV',
        'avoir_premier' => 1,
    ],

    'documents' => [
        'validite_devis_jours' => 30,
        'delai_paiement_jours' => 30,
        'acompte_pourcentage' => 30,
        'page_couverture' => false,
        'mention_dechets' => 'Gestion des déchets du chantier : les déchets sont triés sur place puis évacués vers une installation de collecte adaptée (déchetterie professionnelle ou point de reprise). La quantité estimée et le coût de leur évacuation figurent dans le détail du devis.',
    ],

    'assurance' => [
        'alerte_jours' => 30,
    ],

    'emails' => [
        'serveur' => 'smtp.gmail.com',
        'port' => 587,
        'copie_cachee' => true,
        'modeles' => [
            'devis' => [
                'sujet' => 'Votre devis {numero}',
                'corps' => "{salutation},\n\nVeuillez trouver votre devis {numero} d'un montant de {montant}.\nVous pouvez le consulter et le signer en ligne : {lien}\n\nNous restons à votre disposition pour toute question.\n\nCordialement,\n{entreprise}",
            ],
            'facture' => [
                'sujet' => 'Votre facture {numero}',
                'corps' => "{salutation},\n\nVeuillez trouver votre facture {numero} d'un montant de {montant}, à régler avant le {echeance}.\nConsulter et régler en ligne : {lien}\n\nMerci de votre confiance.\n\nCordialement,\n{entreprise}",
            ],
            'relance' => [
                'sujet' => 'Rappel : facture {numero}',
                'corps' => "{salutation},\n\nSauf erreur de notre part, la facture {numero} d'un montant de {montant} arrivée à échéance le {echeance} reste à régler.\nVous pouvez la régler en ligne : {lien}\n\nSi le règlement a déjà été fait, merci de ne pas tenir compte de ce message.\n\nCordialement,\n{entreprise}",
            ],
            'relance_devis' => [
                'sujet' => 'Votre devis {numero}',
                'corps' => "{salutation},\n\nNous revenons vers vous au sujet de notre devis {numero} d'un montant de {montant}, valable jusqu'au {echeance}.\nVous pouvez le consulter et le signer en ligne : {lien}\n\nAvez-vous des questions ? Nous pouvons en parler au {telephone}.\n\nCordialement,\n{entreprise}",
            ],
            'avis' => [
                'sujet' => 'Votre avis compte pour nous',
                'corps' => "{salutation},\n\nMerci de nous avoir fait confiance pour vos travaux.\nSi vous êtes satisfait, pourriez-vous laisser un petit avis ? Cela nous aide beaucoup : {lien}\n\nCordialement,\n{entreprise}",
            ],
            'entretien' => [
                'sujet' => 'Entretien de votre toiture',
                'corps' => "{salutation},\n\nCela fait environ un an que nous sommes intervenus chez vous.\nUn petit contrôle de la toiture permet d'éviter les mauvaises surprises. Souhaitez-vous que nous passions ?\nIl suffit de répondre à cet email ou de nous appeler au {telephone}.\n\nCordialement,\n{entreprise}",
            ],
            'rendez_vous' => [
                'sujet' => 'Rappel : notre passage le {date}',
                'corps' => "{salutation},\n\nPetit rappel : nous passerons le {date} ({horaire}) pour : {objet}.\nAdresse : {adresse}\n\nEn cas d'empêchement, merci de nous prévenir au {telephone}.\n\nCordialement,\n{entreprise}",
            ],
            'rapport' => [
                'sujet' => 'Rapport d\'intervention',
                'corps' => "{salutation},\n\nVeuillez trouver le rapport de notre intervention : {lien}\n\nCordialement,\n{entreprise}",
            ],
        ],
    ],

    'mypos' => [
        'mode' => 'desactive',
    ],

    'suivi' => [
        'relances_devis' => true,      // relances automatiques à 7 et 15 jours
        'lien_avis' => '',             // lien Google pour laisser un avis (vide = désactivé)
        'entretien_mois' => 12,        // proposer un entretien N mois après un chantier
        'formulaire_actif' => false,   // formulaire public de demande de devis
        'imap_actif' => false,         // lire les emails du site (WordPress)
        'imap_serveur' => 'imap.gmail.com',
        'imap_port' => 993,
        'imap_filtre' => 'WordPress',  // mot cherché dans l'expéditeur ou l'objet
    ],

    'site' => [
        'compteur_actif' => false,     // compteur de visites (balise s.js sur le site)
        'jetpack_client_id' => '',     // application WordPress.com (statistiques Jetpack)
    ],

    'textes_types' => [],

    'clients' => [
        // « Comment nous a-t-il connus ? » (modifiable dans Réglages → Clients).
        'provenances' => [
            'Bouche-à-oreille', 'Site internet', 'Recherche Google', 'Facebook / Instagram', 'Pages Jaunes',
            'Panneau de chantier', 'Véhicule de l\'entreprise', 'Déjà client', 'Recommandé par un professionnel', 'Autre',
        ],
    ],
];
