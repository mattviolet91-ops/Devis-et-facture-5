<?php

namespace App\Support;

use App\Models\User;

/**
 * Accueil et barre du bas personnalisables, pour chaque compte.
 * Le bloc « Aujourd'hui » reste toujours en premier.
 */
class Personnalisation
{
    /** Blocs de l'accueil (après « Aujourd'hui »). */
    public const BLOCS = [
        'taches' => 'Tâches à faire',
        'chiffres' => 'Chiffres clés',
        'activite' => 'Activité récente',
        'astuce' => 'Astuce du jour',
    ];

    /** Boutons possibles aux places 2 et 4 de la barre du bas. */
    public const BOUTONS = [
        'clients' => ['libelle' => 'Clients', 'icone' => 'clients', 'route' => 'clients.index', 'actif' => ['clients.*', 'chantiers.*', 'photos.*', 'rapports.*']],
        'devis' => ['libelle' => 'Devis', 'icone' => 'devis', 'route' => 'devis.index', 'actif' => ['devis.*', 'envoi.*']],
        'planning' => ['libelle' => 'Planning', 'icone' => 'planning', 'route' => 'planning.index', 'actif' => ['planning.*']],
        'factures' => ['libelle' => 'Factures', 'icone' => 'factures', 'route' => 'factures.index', 'actif' => ['factures.*'], 'gerant' => true],
        'suivi' => ['libelle' => 'Suivi', 'icone' => 'cloche', 'route' => 'suivi', 'actif' => ['suivi*']],
    ];

    /**
     * Blocs affichés, dans l'ordre choisi.
     *
     * @return list<string>
     */
    public static function blocs(User $user): array
    {
        $choix = $user->preference('accueil.blocs');
        $blocs = is_array($choix) ? array_values(array_intersect($choix, array_keys(self::BLOCS))) : array_keys(self::BLOCS);

        return array_values(array_filter($blocs, fn (string $b) => $b !== 'astuce' || ! $user->preference('accueil.sans_astuce')));
    }

    /**
     * Les deux boutons choisis (places 2 et 4).
     *
     * @return array{0: string, 1: string}
     */
    public static function boutons(User $user): array
    {
        $permis = array_keys(self::boutonsPermis($user));
        $choix = (array) $user->preference('barre.boutons', ['clients', 'devis']);
        $choix = array_values(array_unique(array_intersect($choix, $permis)));

        foreach (['clients', 'devis', 'planning'] as $defaut) {
            if (count($choix) >= 2) {
                break;
            }
            if (! in_array($defaut, $choix, true)) {
                $choix[] = $defaut;
            }
        }

        return [$choix[0], $choix[1]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function boutonsPermis(User $user): array
    {
        return array_filter(self::BOUTONS, fn (array $b) => empty($b['gerant']) || $user->estGerant());
    }
}
