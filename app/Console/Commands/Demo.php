<?php

namespace App\Console\Commands;

use App\Models\Appareil;
use App\Models\User;
use App\Notifications\NouvelAppareil;
use App\Support\Journal;
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
        'identite.site' => '',
        'identite.siret' => '00000000000000',
        'identite.code_ape' => '4391B',
        'identite.tva_intracom' => '',
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
            $appareil = Appareil::firstOrCreate(
                ['user_id' => $gerant->id, 'empreinte' => hash('sha256', 'demo-tablette')],
                ['libelle' => 'Android – Chrome', 'last_seen_at' => now()],
            );
            $gerant->notifyNow(new NouvelAppareil($gerant, $appareil), ['database']);
        });

        $this->info('Données de démonstration installées (entreprise et comptes fictifs).');
        $this->line('Notez ces mots de passe : ils ne seront plus affichés.');
        $this->table(['Compte', 'Email', 'Mot de passe'], [
            ['Gérant', 'demo@exemple.test', $motsDePasse['demo@exemple.test']],
            ['Commercial', 'commercial@exemple.test', $motsDePasse['commercial@exemple.test']],
        ]);

        return self::SUCCESS;
    }
}
