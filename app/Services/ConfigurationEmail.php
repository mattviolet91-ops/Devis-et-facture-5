<?php

namespace App\Services;

use App\Support\Reglages;

/**
 * Applique le compte Gmail des Réglages à l'envoi des emails (SMTP).
 * Le mot de passe d'application est stocké chiffré et n'est jamais affiché.
 */
class ConfigurationEmail
{
    public function __construct(private Reglages $reglages) {}

    public function estConfiguree(): bool
    {
        return (string) reglage('emails.adresse') !== '' && $this->reglages->aSecret('emails.mot_de_passe');
    }

    /**
     * @param  bool  $forcer  Utiliser Gmail même si un autre envoi est prévu (test d'envoi).
     */
    public function appliquer(bool $forcer = false): void
    {
        $nom = (string) (reglage('emails.nom_expediteur') ?: reglage('identite.nom_commercial') ?: config('app.name'));

        if (! $this->estConfiguree()) {
            if ((string) reglage('identite.email') !== '') {
                config(['mail.from' => ['address' => reglage('identite.email'), 'name' => $nom]]);
            }

            return;
        }

        config([
            'mail.mailers.gmail' => [
                'transport' => 'smtp',
                'scheme' => 'smtp',
                'host' => (string) reglage('emails.serveur'),
                'port' => (int) reglage('emails.port'),
                'username' => (string) reglage('emails.adresse'),
                'password' => $this->reglages->getSecret('emails.mot_de_passe'),
                'timeout' => 20,
            ],
            'mail.from' => ['address' => (string) reglage('emails.adresse'), 'name' => $nom],
        ]);

        // Pendant les tests automatiques, rien ne part réellement (sauf test forcé, simulé).
        if ($forcer || ! app()->runningUnitTests()) {
            config(['mail.default' => 'gmail']);
            app('mail.manager')->forgetMailers();
        }
    }
}
