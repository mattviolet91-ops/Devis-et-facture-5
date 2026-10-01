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
            'rapport' => [
                'sujet' => 'Rapport d\'intervention',
                'corps' => "{salutation},\n\nVeuillez trouver le rapport de notre intervention : {lien}\n\nCordialement,\n{entreprise}",
            ],
        ],
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
