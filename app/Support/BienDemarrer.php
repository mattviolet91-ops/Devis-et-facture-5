<?php

namespace App\Support;

use App\Models\AbonnementPush;
use App\Models\Prestation;
use App\Models\User;
use App\Services\ConfigurationEmail;
use App\Services\Sauvegardes;
use Illuminate\Support\Carbon;

/**
 * Liste « Bien démarrer », vérifiée automatiquement (gérant).
 */
class BienDemarrer
{
    /**
     * @return list<array{texte: string, fait: bool, lien: string}>
     */
    public static function points(User $user): array
    {
        $fin = reglage('assurance.date_fin');

        return [
            ['texte' => 'Coordonnées de l\'entreprise (nom, adresse, SIRET, téléphone, email)', 'lien' => route('reglages.edit', 'entreprise'),
                'fait' => collect(['identite.nom_commercial', 'identite.adresse', 'identite.ville', 'identite.siret', 'identite.telephone', 'identite.email'])->every(fn ($c) => trim((string) reglage($c)) !== '')],
            ['texte' => 'IBAN pour les virements', 'lien' => route('reglages.edit', 'documents'), 'fait' => trim((string) reglage('documents.iban')) !== ''],
            ['texte' => 'Assurance décennale à jour', 'lien' => route('reglages.edit', 'assurance'), 'fait' => $fin && now()->startOfDay()->lte(Carbon::parse((string) $fin))],
            ['texte' => 'Conditions générales de vente', 'lien' => route('reglages.edit', 'documents'), 'fait' => trim((string) reglage('documents.cgv')) !== ''],
            ['texte' => 'Au moins 5 prestations dans le catalogue', 'lien' => route('catalogue.index'), 'fait' => Prestation::count() >= 5],
            ['texte' => 'Envoi des emails réglé (Gmail)', 'lien' => route('reglages.edit', 'emails'), 'fait' => app(ConfigurationEmail::class)->estConfiguree()],
            ['texte' => 'Notifications activées sur ce téléphone', 'lien' => route('plus'), 'fait' => AbonnementPush::where('user_id', $user->id)->exists()],
            ['texte' => 'Sauvegarde faite ce mois-ci', 'lien' => route('reglages.sauvegardes'), 'fait' => (bool) app(Sauvegardes::class)->derniere()?->isSameMonth(now())],
        ];
    }

    public static function complet(User $user): bool
    {
        return collect(self::points($user))->every('fait');
    }
}
