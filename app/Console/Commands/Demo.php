<?php

namespace App\Console\Commands;

use App\Models\Appareil;
use App\Models\Devis;
use App\Models\Prestation;
use App\Models\User;
use App\Notifications\NouvelAppareil;
use App\Services\CatalogueDepart;
use App\Support\Configuration;
use App\Support\DonneesDemo;
use App\Support\Journal;
use App\Support\Metiers;
use App\Support\Reglages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remplit une installation de DÉMONSTRATION avec des données 100 % fictives.
 * Jamais en production : une vraie entreprise démarre toujours sur une base vide.
 */
class Demo extends Command
{
    protected $signature = 'app:demo {--force : Accepter même si APP_ENV=production}';

    protected $description = 'Remplit l\'application avec une entreprise et des comptes fictifs (démonstration)';

    /**
     * Entreprise fictive : domaine .test réservé, numéros factices.
     *
     * @var array<string, string>
     */
    public const ENTREPRISE = [
        'identite.nom_commercial' => 'Couverture Démo',
        'identite.forme_juridique' => 'EURL',
        'identite.adresse' => '1 rue de l\'Exemple',
        'identite.code_postal' => '00000',
        'identite.ville' => 'Ville-Démo',
        'identite.telephone' => '01 00 00 00 00',
        'identite.email' => 'contact@couverture-demo.test',
        'identite.site' => 'https://www.couverture-demo.test',
        'identite.siret' => '00000000000000',
        'identite.code_ape' => '4391B',
        'identite.tva_intracom' => '',
        'identite.mediateur_nom' => 'Médiateur fictif de la consommation',
        'tva.regime' => 'franchise',
        'documents.iban' => 'FR7630006000011234567890189',
        'documents.bic' => 'AGRIFRPP',
        'assurance.assureur' => 'Assureur fictif',
        'assurance.numero_contrat' => 'DEMO-0000',
        'assurance.activites' => "Couverture\nZinguerie\nCharpente (démonstration)",
        'assurance.zone' => 'France métropolitaine',
    ];

    public function handle(Reglages $reglages): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusé : APP_ENV=production. Les données de démonstration ne vont jamais chez une vraie entreprise.');

            return self::FAILURE;
        }

        if (User::exists() && ! $this->confirm('Des comptes existent déjà. Ajouter quand même les données de démonstration ?', false)) {
            return self::FAILURE;
        }

        $motsDePasse = [];

        DB::transaction(function () use ($reglages, &$motsDePasse) {
            foreach (self::ENTREPRISE as $cle => $valeur) {
                $reglages->set($cle, $valeur);
            }
            $reglages->set('demo.active', true);
            $reglages->set('entreprise.metier', 'couvreur');
            $reglages->set('modules.actifs', Metiers::metier('couvreur')['modules']);
            $reglages->set('documents.cgv', Metiers::cgvDeDepart('couvreur'));
            $reglages->set('setup.etapes', array_keys(Configuration::ETAPES));
            $reglages->set('setup.completed_at', now()->toIso8601String());
            $reglages->set('tva.taux_defaut', 0);
            $reglages->set('assurance.date_debut', now()->startOfYear()->toDateString());
            $reglages->set('assurance.date_fin', now()->addDays(20)->toDateString());
            $reglages->set('textes_types', [
                ['titre' => 'Accès au chantier', 'texte' => 'Accès au toit par échafaudage installé par nos soins.'],
                ['titre' => 'Météo', 'texte' => 'Les dates d\'intervention peuvent être décalées en cas de pluie ou de vent fort.'],
            ]);

            foreach (['demo@exemple.test' => User::ROLE_GERANT, 'commercial@exemple.test' => User::ROLE_COMMERCIAL] as $email => $role) {
                $motDePasse = Str::password(14, symbols: false);
                $motsDePasse[$email] = $motDePasse;

                $user = User::updateOrCreate(['email' => $email], [
                    'password' => $motDePasse,
                    'role' => $role,
                    'is_active' => true,
                ]);

                Journal::ecrire('compte.creation', 'Compte de démonstration : '.$email, $user, $user->id);
            }

            $gerant = User::where('email', 'demo@exemple.test')->first();
            DonneesDemo::installerClients($gerant);
            app(CatalogueDepart::class)->charger('couvreur', DonneesDemo::PRIX_CATALOGUE);
            DonneesDemo::installerDevis($gerant);
            DonneesDemo::installerFactures($gerant);
            DonneesDemo::installerPlanning($gerant);
            DonneesDemo::installerPhotos($gerant);
            DonneesDemo::installerDemandes();
            // Un devis envoyé il y a 9 jours, déjà relancé une fois (pour montrer le suivi).
            Devis::where('statut', 'envoye')->oldest('id')->first()
                ?->forceFill(['envoye_at' => now()->subDays(9), 'date_devis' => now()->subDays(9), 'relances' => 1, 'derniere_relance_at' => now()->subDays(2)])->save();
            $reglages->set('suivi.formulaire_actif', true);
            $reglages->set('site.compteur_actif', true);
            DonneesDemo::installerStatistiques($gerant);
            Prestation::where('nom', 'Échafaudage')->update(['description' => 'Montage, location et démontage (prix d\'exemple).']);
            $appareil = Appareil::firstOrCreate(
                ['user_id' => $gerant->id, 'empreinte' => hash('sha256', 'demo-tablette')],
                ['libelle' => 'Android – Chrome', 'last_seen_at' => now()],
            );
            $gerant->notifyNow(new NouvelAppareil($gerant, $appareil), ['database']);
        });

        $this->callSilently('app:alerte-assurance');

        $this->info('Données de démonstration installées (entreprise et comptes fictifs).');
        $this->line('Notez ces mots de passe : ils ne seront plus affichés.');
        $this->table(['Compte', 'Email', 'Mot de passe'], [
            ['Gérant', 'demo@exemple.test', $motsDePasse['demo@exemple.test']],
            ['Commercial', 'commercial@exemple.test', $motsDePasse['commercial@exemple.test']],
        ]);

        return self::SUCCESS;
    }
}
