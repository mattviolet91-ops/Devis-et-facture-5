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
|
*/

return [
    'identite' => [
        'nom_commercial' => '',
        'forme_juridique' => '',
        'adresse' => '',
        'code_postal' => '',
        'ville' => '',
        'telephone' => '',
        'email' => '',
        'site' => '',
        'siret' => '',
        'code_ape' => '',
        'tva_intracom' => '',
    ],

    'setup' => [
        'completed_at' => null,
    ],
];
