<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Devis;
use App\Models\User;
use App\Services\DevisExpress;
use App\Services\GestionDevis;

/**
 * Données d'EXEMPLE pour la démonstration : personnes et adresses inventées,
 * numéros en 06 00 00…, emails sur le domaine réservé .test.
 */
class DonneesDemo
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function clients(): array
    {
        return [
            ['type' => 'particulier', 'civilite' => 'Mme', 'prenom' => 'Sophie', 'nom' => 'Martin', 'telephone' => '0600000001', 'email' => 'sophie.martin@exemple.test',
                'adresse' => '12 rue des Lilas', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'provenance' => 'Recherche Google',
                'chantiers' => [['libelle' => 'Maison', 'adresse' => '12 rue des Lilas', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'type_toiture' => 'Tuiles mécaniques', 'surface' => 120, 'pente' => 35, 'acces' => 'Échafaudage nécessaire'],
                ],
                'notes' => ['Toiture très moussue côté nord. Souhaite un devis démoussage et traitement.'],
            ],
            ['type' => 'particulier', 'civilite' => 'M. et Mme', 'prenom' => 'Hélène et Éric', 'nom' => 'Bérard', 'telephone' => '0600000002', 'email' => 'berard@exemple.test',
                'adresse' => '3 chemin du Moulin', 'code_postal' => '00200', 'ville' => 'Saint-Exemple', 'provenance' => 'Bouche-à-oreille', 'provenance_detail' => 'Recommandés par Mme Martin',
                'chantiers' => [
                    ['libelle' => 'Maison principale', 'adresse' => '3 chemin du Moulin', 'code_postal' => '00200', 'ville' => 'Saint-Exemple', 'type_toiture' => 'Ardoises naturelles', 'surface' => 95.5, 'pente' => 45, 'acces' => 'Nacelle nécessaire (rue étroite)'],
                    ['libelle' => 'Grange', 'adresse' => '5 chemin du Moulin', 'code_postal' => '00200', 'ville' => 'Saint-Exemple', 'type_toiture' => 'Tuiles canal', 'surface' => 60, 'pente' => 20, 'acces' => 'Facile (plain-pied)'],
                ],
                'notes' => ['Fuite au niveau de la cheminée après l\'orage.'],
            ],
            ['type' => 'particulier', 'civilite' => 'Mme', 'prenom' => 'Élodie', 'nom' => 'Petit', 'telephone' => '0600000003', 'email' => null,
                'adresse' => '8 impasse des Tilleuls', 'code_postal' => '00300', 'ville' => 'Bourg-Fictif', 'provenance' => 'Panneau de chantier',
                'chantiers' => [['adresse' => '8 impasse des Tilleuls', 'code_postal' => '00300', 'ville' => 'Bourg-Fictif', 'type_toiture' => 'Tuiles plates', 'surface' => 80, 'pente' => 50, 'acces' => 'Échelle suffisante']],
                'notes' => [],
            ],
            ['type' => 'particulier', 'civilite' => 'M.', 'prenom' => 'Jérôme', 'nom' => 'Durand', 'telephone' => '0600000004', 'email' => 'j.durand@exemple.test',
                'adresse' => '21 avenue de la Gare', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'provenance' => 'Site internet',
                'chantiers' => [['adresse' => '21 avenue de la Gare', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'type_toiture' => 'Zinc', 'surface' => 45, 'pente' => 10, 'acces' => 'Échelle suffisante']],
                'notes' => ['Gouttières à remplacer, préfère être appelé après 18 h.'],
            ],
            ['type' => 'particulier', 'civilite' => 'M. et Mme', 'prenom' => null, 'nom' => 'Leroy', 'telephone' => '0600000005', 'email' => 'famille.leroy@exemple.test',
                'adresse' => '2 place de l\'Église', 'code_postal' => '00400', 'ville' => 'Village-Test', 'provenance' => 'Facebook / Instagram',
                'chantiers' => [], 'notes' => [],
            ],
            ['type' => 'professionnel', 'raison_sociale' => 'SCI Les Tilleuls (fictive)', 'civilite' => 'M.', 'prenom' => 'Paul', 'nom' => 'Moreau', 'telephone' => '0600000006', 'email' => 'contact@sci-tilleuls.test',
                'adresse' => '40 boulevard Fictif', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'provenance' => 'Déjà client',
                'chantiers' => [['libelle' => 'Immeuble rue Haute', 'adresse' => '14 rue Haute', 'code_postal' => '00100', 'ville' => 'Ville-Démo', 'type_toiture' => 'Bac acier', 'surface' => 310, 'pente' => 8, 'acces' => 'Nacelle nécessaire']],
                'notes' => ['Facturation au nom de la SCI, envoyer les factures par email.'],
            ],
            ['type' => 'professionnel', 'raison_sociale' => 'Cabinet Syndic Exemple', 'civilite' => 'Mme', 'prenom' => 'Claire', 'nom' => 'Fontaine', 'telephone' => '0600000007', 'email' => 'gestion@syndic-exemple.test',
                'adresse' => '9 rue du Commerce', 'code_postal' => '00500', 'ville' => 'Cité-Exemple', 'provenance' => 'Recommandé par un professionnel',
                'chantiers' => [], 'notes' => [],
            ],
            ['type' => 'particulier', 'civilite' => 'Mme', 'prenom' => 'Anaïs', 'nom' => 'Garnier', 'telephone' => '0600000008', 'email' => 'anais.garnier@exemple.test',
                'adresse' => '17 route des Vignes', 'code_postal' => '00600', 'ville' => 'Les Exemples', 'provenance' => 'Pages Jaunes',
                'chantiers' => [['adresse' => '17 route des Vignes', 'code_postal' => '00600', 'ville' => 'Les Exemples', 'type_toiture' => 'Tuiles mécaniques', 'surface' => 140, 'pente' => 30, 'acces' => 'Échafaudage nécessaire']],
                'notes' => [],
            ],
        ];
    }

    /**
     * Prix FICTIFS (en centimes HT) du catalogue de démonstration.
     *
     * @var array<string, int>
     */
    public const PRIX_CATALOGUE = [
        'Démoussage de toiture' => 1200, 'Traitement hydrofuge' => 900, 'Nettoyage des gouttières' => 600,
        'Remplacement de tuiles' => 3500, 'Réfection de couverture en tuiles' => 9500, 'Réfection de couverture en ardoises' => 13500,
        'Pose d\'écran sous toiture' => 2500, 'Faîtière scellée' => 4500, 'Faîtage à sec ventilé' => 5500,
        'Gouttière zinc' => 6500, 'Descente d\'eau pluviale' => 5000, 'Abergement de cheminée' => 85000,
        'Remplacement de chevrons' => 4500, 'Pose de fenêtre de toit' => 95000, 'Échafaudage' => 60000,
        'Évacuation des déchets' => 15000, 'Recherche de fuite' => 18000, 'Main-d\'œuvre' => 4500,
    ];

    public static function installerClients(User $auteur): int
    {
        foreach (self::clients() as $i => $donnees) {
            $chantiers = $donnees['chantiers'];
            $notes = $donnees['notes'];
            unset($donnees['chantiers'], $donnees['notes']);

            $client = Client::updateOrCreate(['email' => $donnees['email'], 'telephone' => $donnees['telephone']], $donnees + ['created_by' => $auteur->id]);
            $client->forceFill(['created_at' => now()->subDays(60 - $i * 7)])->save();

            if (! $client->chantiers()->exists()) {
                foreach ($chantiers as $chantier) {
                    $client->chantiers()->create($chantier);
                }
                foreach ($notes as $texte) {
                    $client->notes()->create(['texte' => $texte, 'user_id' => $auteur->id]);
                }
            }
        }

        return count(self::clients());
    }

    /**
     * Devis d'exemple créés avec le devis express (phrases fictives), à différentes étapes.
     */
    public static function installerDevis(User $auteur): int
    {
        if (Devis::exists()) {
            return 0;
        }

        $express = app(DevisExpress::class);
        $gestion = app(GestionDevis::class);
        $exemples = [
            ['Mme Martin, démoussage de toiture 120 m² à 12 €, traitement hydrofuge 120 m² à 9 €, échafaudage forfait 600 €', 'accepte', 'Démoussage et traitement de la toiture'],
            ['Bérard, abergement de cheminée forfait 850 €, recherche de fuite forfait 180 €', 'envoye', 'Fuite au niveau de la cheminée'],
            ['Jérôme Durand, gouttière zinc 18 ml à 65 €, descente d\'eau pluviale 6 ml à 50 €', 'brouillon', 'Remplacement des gouttières'],
            ['Petit, réfection de couverture en tuiles 80 m² à 95 €, évacuation des déchets forfait 150 €', 'refuse', 'Réfection de la couverture'],
            ['SCI Les Tilleuls, nettoyage toiture bac acier 310 m² à 4 €', 'envoye', 'Entretien de la toiture'],
        ];

        $total = 0;
        foreach ($exemples as [$phrase, $statut, $objet]) {
            $resultat = $express->analyser($phrase);
            if ($resultat['erreurs'] || ! $resultat['client']) {
                continue;
            }
            $devis = $gestion->creer($resultat['client'], $resultat['client']->chantiers()->first(), $objet, $auteur->id);
            $devis->update(['dechets_estimation' => 'Environ 1 m³ de déchets de chantier, évacués en déchetterie professionnelle (exemple).']);
            $gestion->remplacerLignes($devis, $resultat['lignes']);

            if ($statut !== 'brouillon') {
                $gestion->marquerEnvoye($devis);
            }
            if ($statut === 'accepte') {
                $gestion->accepter($devis);
            } elseif ($statut === 'refuse') {
                $gestion->refuser($devis, 'Budget trop élevé pour cette année (exemple).');
            }
            $total++;
        }

        return $total;
    }
}
