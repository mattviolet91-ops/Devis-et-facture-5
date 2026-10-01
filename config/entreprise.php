<?php

/*
|--------------------------------------------------------------------------
| Réglages de l'entreprise : valeurs par défaut
|--------------------------------------------------------------------------
|
| Ces valeurs sont TOUJOURS vides. Les informations de l'entreprise sont
| saisies dans l'application (menu de configuration puis Réglages) et
| enregistrées dans la table « settings ». Ne jamais écrire ici le nom,
| l'adresse, le SIRET ou les coordonnées d'une entreprise.
| (Les conventions de l'application sont dans config/reglages.php.)
|
*/

return [
    'identite' => [
        'nom_commercial' => '',
        'forme_juridique' => '',
        'capital' => '',
        'adresse' => '',
        'code_postal' => '',
        'ville' => '',
        'telephone' => '',
        'email' => '',
        'site' => '',
        'siret' => '',
        'code_ape' => '',
        'rcs_rm' => '',
        'tva_intracom' => '',
        'agrements' => '',
        'mediateur_nom' => '',
        'mediateur_site' => '',
    ],

    'apparence' => [
        'logo' => '',
        'icone' => '',
    ],

    'documents' => [
        'iban' => '',
        'bic' => '',
        'cgv' => '',
    ],

    'assurance' => [
        'assureur' => '',
        'numero_contrat' => '',
        'date_debut' => '',
        'date_fin' => '',
        'activites' => '',
        'zone' => '',
        'attestation' => '',
    ],

    'emails' => [
        'adresse' => '',
        'nom_expediteur' => '',
    ],

    'setup' => [
        'completed_at' => null,
    ],
];
