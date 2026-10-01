<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Photo;
use App\Models\Rapport;
use App\Models\RendezVous;
use App\Models\User;
use App\Models\VisiteSite;
use App\Services\DevisExpress;
use App\Services\Encaissements;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Services\Photos;
use Illuminate\Support\Carbon;

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

    /**
     * Factures d'exemple : un acompte sur le devis accepté, une facture en retard.
     */
    public static function installerFactures(User $auteur): int
    {
        if (Facture::exists()) {
            return 0;
        }

        $gestion = app(GestionFactures::class);
        $total = 0;

        $accepte = Devis::where('statut', 'accepte')->first();
        if ($accepte) {
            $acompte = $gestion->depuisDevis($accepte, Facture::ACOMPTE, 3000, $auteur->id);
            $gestion->emettre($acompte);
            // Acompte réglé par virement (exemple).
            app(Encaissements::class)->enregistrer($acompte->fresh(), $acompte->fresh()->resteAPayer(), 'virement', now()->toDateString(), 'Virement (exemple)', null, $auteur->id);
            $total++;
        }

        $client = Client::where('nom', 'Garnier')->first();
        if ($client) {
            // Facture émise il y a 45 jours (dates fictives) : elle apparaît « en retard ».
            Carbon::setTestNow(now()->subDays(45));
            try {
                $facture = $gestion->creerVide($client, $auteur->id);
                $facture->update(['objet' => 'Recherche de fuite et réparation (exemple)']);
                $gestion->remplacerLignes($facture, [
                    ['type' => 'ligne', 'designation' => 'Recherche de fuite', 'quantite' => 1000, 'unite' => 'forfait', 'prix_unitaire_ht' => 18000],
                    ['type' => 'ligne', 'designation' => 'Remplacement de tuiles', 'quantite' => 8000, 'unite' => 'u', 'prix_unitaire_ht' => 3500],
                ]);
                $gestion->emettre($facture);
            } finally {
                Carbon::setTestNow();
            }
            $total++;
        }

        return $total;
    }

    /**
     * Rendez-vous et chantiers d'exemple autour d'aujourd'hui.
     * Le devis accepté reste « à planifier » pour montrer la liste.
     */
    public static function installerPlanning(User $auteur): int
    {
        if (RendezVous::exists()) {
            return 0;
        }

        $client = fn (string $nom) => Client::where('nom', $nom)->first();
        $commercial = User::where('role', User::ROLE_COMMERCIAL)->first();
        $exemples = [
            ['rdv', 'Visite pour devis : fuite', 'Leroy', today()->setTime(14, 0), today()->setTime(15, 0), false, $auteur->id, null],
            ['rdv', 'Mesures pour les gouttières', 'Durand', today()->addDay()->setTime(10, 0), today()->addDay()->setTime(11, 0), false, $commercial?->id, 1],
            ['chantier', 'Abergement de cheminée', 'Bérard', today()->addWeek()->startOfWeek()->addDay(), today()->addWeek()->startOfWeek()->addDays(2)->setTime(23, 59, 59), true, $auteur->id, 2],
            ['chantier', 'Recherche de fuite et réparation', 'Garnier', today()->subDays(46), today()->subDays(46)->setTime(23, 59, 59), true, $auteur->id, null],
            ['rdv', 'Entretien annuel de la toiture', 'Moreau', today()->addDays(9)->setTime(8, 30), today()->addDays(9)->setTime(10, 0), false, $auteur->id, null],
        ];

        $total = 0;
        foreach ($exemples as [$type, $titre, $nom, $debut, $fin, $journee, $userId, $rappel]) {
            $c = $client($nom);
            if (! $c) {
                continue;
            }
            RendezVous::create([
                'type' => $type, 'titre' => $titre, 'debut' => $debut, 'fin' => $fin, 'journee_entiere' => $journee,
                'client_id' => $c->id, 'chantier_id' => $c->chantiers()->value('id'), 'user_id' => $userId,
                'rappel_client_jours' => $rappel, 'fait' => $debut->isPast() && $journee, 'created_by' => $auteur->id,
                'notes' => $type === 'chantier' ? 'Prévoir l\'échafaudage la veille (exemple).' : null,
            ]);
            $total++;
        }

        return $total;
    }

    /**
     * Photos dessinées (aucune vraie maison) et un rapport d'intervention d'exemple.
     */
    public static function installerPhotos(User $auteur): int
    {
        if (Photo::exists()) {
            return 0;
        }

        $service = app(Photos::class);
        $total = 0;
        $exemples = [
            ['Garnier', 'avant', 'Tuiles cassées côté jardin (exemple)', true],
            ['Garnier', 'apres', 'Tuiles remplacées (exemple)', true],
            ['Martin', 'avant', 'Mousse sur la toiture (exemple)', true],
        ];
        foreach ($exemples as [$nom, $moment, $legende, $documents]) {
            $client = Client::where('nom', $nom)->first();
            if (! $client) {
                continue;
            }
            $image = self::dessinerToit($moment);
            $service->enregistrerImage($image, [
                'client_id' => $client->id, 'chantier_id' => $client->chantiers()->value('id'),
                'rendez_vous_id' => RendezVous::where('client_id', $client->id)->value('id'),
                'moment' => $moment, 'legende' => $legende, 'dans_documents' => $documents, 'user_id' => $auteur->id,
            ]);
            imagedestroy($image);
            $total++;
        }

        $client = Client::where('nom', 'Garnier')->first();
        if ($client) {
            Rapport::create([
                'client_id' => $client->id, 'chantier_id' => $client->chantiers()->value('id'),
                'rendez_vous_id' => RendezVous::where('client_id', $client->id)->value('id'),
                'date_intervention' => today()->subDays(46), 'titre' => 'Recherche de fuite et réparation',
                'travaux' => "Recherche de la fuite au-dessus de la chambre.\nRemplacement de 8 tuiles cassées.",
                'constats' => 'Plusieurs tuiles fendues par le gel. Liteaux en bon état (exemple).',
                'conseils' => 'Prévoir un démoussage d\'ici 2 ans (exemple).',
                'photos' => Photo::where('client_id', $client->id)->pluck('id')->all(),
                'created_by' => $auteur->id,
            ]);
        }

        return $total;
    }

    /**
     * Image simple : ciel, toit et mention « EXEMPLE ».
     */
    private static function dessinerToit(string $moment): \GdImage
    {
        $image = imagecreatetruecolor(1200, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 168, 205, 235));
        imagefilledrectangle($image, 0, 700, 1200, 900, imagecolorallocate($image, 110, 160, 90));
        imagefilledrectangle($image, 250, 450, 950, 760, imagecolorallocate($image, 230, 220, 200));
        $tuiles = $moment === 'avant' ? imagecolorallocate($image, 120, 110, 80) : imagecolorallocate($image, 180, 70, 45);
        imagefilledpolygon($image, [200, 470, 600, 180, 1000, 470], $tuiles);
        $lignes = imagecolorallocate($image, 90, 50, 35);
        for ($y = 230; $y < 470; $y += 30) {
            imageline($image, 600 - (int) (($y - 180) * 400 / 290), $y, 600 + (int) (($y - 180) * 400 / 290), $y, $lignes);
        }
        $noir = imagecolorallocate($image, 30, 30, 30);
        imagestring($image, 5, 40, 40, 'EXEMPLE - '.mb_strtoupper(['avant' => 'avant', 'pendant' => 'pendant', 'apres' => 'apres'][$moment]), $noir);

        return $image;
    }

    /**
     * Demandes de devis d'exemple (formulaire du site).
     */
    public static function installerDemandes(): int
    {
        if (Demande::exists()) {
            return 0;
        }

        Demande::create(['source' => 'formulaire', 'nom' => 'Lucas Exemple', 'telephone' => '0600000011', 'email' => 'lucas@exemple.test', 'ville' => 'Ville-Démo',
            'message' => 'Bonjour, des tuiles ont glissé après le coup de vent. Pouvez-vous passer ? (exemple)', 'recue_at' => now()->subHours(3)]);
        Demande::create(['source' => 'email', 'nom' => 'Nadia Test', 'telephone' => '0600000012', 'ville' => 'Village-Test',
            'message' => 'Devis pour remplacement de gouttières, maison de plain-pied. (exemple)', 'message_id' => 'demo-1@exemple.test', 'recue_at' => now()->subDay()]);

        return 2;
    }

    /**
     * Visites fictives du site (30 jours) et un frais sur une facture, pour montrer les statistiques.
     */
    public static function installerStatistiques(User $auteur): void
    {
        if (! VisiteSite::exists()) {
            $sources = ['Google', 'Google', 'Accès direct', 'Facebook', 'Pages Jaunes'];
            $pages = ['/', '/couverture', '/zinguerie', '/contact', '/realisations'];
            $appareils = ['Téléphone', 'Téléphone', 'Ordinateur', 'Tablette'];
            $lignes = [];
            for ($j = 29; $j >= 0; $j--) {
                $jour = today()->subDays($j)->toDateString();
                $nombre = 3 + (($j * 7) % 9);
                for ($v = 0; $v < $nombre; $v++) {
                    $empreinte = substr(hash('sha256', 'demo'.$jour.$v), 0, 16);
                    $lignes[] = ['jour' => $jour, 'empreinte' => $empreinte, 'evenement' => 'vue', 'page' => $pages[($v + $j) % 5], 'source' => $sources[($v * 3 + $j) % 5], 'appareil' => $appareils[($v + $j) % 4], 'created_at' => now()];
                    if (($v + $j) % 6 === 0) {
                        $lignes[] = ['jour' => $jour, 'empreinte' => $empreinte, 'evenement' => ['appeler', 'devis', 'email', 'whatsapp'][($v + $j) % 4], 'page' => '/contact', 'source' => null, 'appareil' => $appareils[($v + $j) % 4], 'created_at' => now()];
                    }
                }
            }
            foreach (array_chunk($lignes, 200) as $paquet) {
                VisiteSite::insert($paquet);
            }
        }

        $facture = Facture::where('type', '!=', Facture::AVOIR)->whereNotNull('numero')->oldest('id')->first();
        if ($facture && ! $facture->frais()->exists()) {
            $facture->frais()->create(['libelle' => 'Tuiles et crochets (exemple)', 'categorie' => 'materiaux', 'montant_ttc' => 38650, 'date_frais' => today()->subDays(3), 'user_id' => $auteur->id]);
            $facture->frais()->create(['libelle' => 'Location échafaudage (exemple)', 'categorie' => 'location', 'montant_ttc' => 42000, 'date_frais' => today()->subDays(2), 'user_id' => $auteur->id]);
        }
    }
}
