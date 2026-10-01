<?php

namespace App\Support;

/**
 * Menu de configuration du premier lancement.
 *
 * - Terminé (setup.completed_at) : tout est ouvert, y compris les pages clients.
 * - Passé par le gérant (setup.passe_at) : l'application s'utilise, un bandeau
 *   rappelle de terminer, et les pages destinées aux clients restent coupées.
 * - Ni l'un ni l'autre : l'application renvoie vers le menu de configuration.
 */
class Configuration
{
    /**
     * Écrans dans l'ordre. « facultatif » : peut être laissé pour plus tard.
     *
     * @var array<string, array{titre: string, facultatif: bool}>
     */
    public const ETAPES = [
        'metier' => ['titre' => 'Votre métier', 'facultatif' => false],
        'identite' => ['titre' => 'Votre entreprise', 'facultatif' => false],
        'tva' => ['titre' => 'TVA', 'facultatif' => false],
        'assurance' => ['titre' => 'Assurance décennale', 'facultatif' => false],
        'documents' => ['titre' => 'Devis et factures', 'facultatif' => false],
        'apparence' => ['titre' => 'Apparence', 'facultatif' => true],
        'emails' => ['titre' => 'Emails', 'facultatif' => true],
        'cgv' => ['titre' => 'Conditions générales de vente', 'facultatif' => false],
        'recapitulatif' => ['titre' => 'Récapitulatif', 'facultatif' => false],
    ];

    public static function estTerminee(): bool
    {
        return ! empty(reglage('setup.completed_at'));
    }

    public static function estPassee(): bool
    {
        return ! empty(reglage('setup.passe_at'));
    }

    public static function bloquee(): bool
    {
        return ! self::estTerminee() && ! self::estPassee();
    }

    /**
     * Les liens envoyés aux clients et le formulaire public ne marchent
     * qu'une fois la configuration terminée (mentions obligatoires présentes).
     */
    public static function accesClientsActif(): bool
    {
        return self::estTerminee();
    }

    /**
     * @return list<string>
     */
    public static function etapesFaites(): array
    {
        return array_values((array) reglage('setup.etapes', []));
    }

    public static function marquerFaite(string $etape): void
    {
        $faites = self::etapesFaites();
        if (! in_array($etape, $faites, true)) {
            $faites[] = $etape;
            app(Reglages::class)->set('setup.etapes', $faites);
        }
    }

    public static function estFaite(string $etape): bool
    {
        return in_array($etape, self::etapesFaites(), true);
    }

    /**
     * Premier écran pas encore fait (pour reprendre là où on s'était arrêté).
     */
    public static function etapeAReprendre(): string
    {
        foreach (array_keys(self::ETAPES) as $etape) {
            if ($etape !== 'recapitulatif' && ! self::estFaite($etape)) {
                return $etape;
            }
        }

        return 'recapitulatif';
    }

    /**
     * Écrans obligatoires pas encore faits.
     *
     * @return list<string>
     */
    public static function manquantes(): array
    {
        return array_values(array_filter(
            array_keys(self::ETAPES),
            fn (string $e) => $e !== 'recapitulatif' && ! self::ETAPES[$e]['facultatif'] && ! self::estFaite($e),
        ));
    }

    public static function numero(string $etape): int
    {
        return array_search($etape, array_keys(self::ETAPES), true) + 1;
    }

    public static function precedente(string $etape): ?string
    {
        $cles = array_keys(self::ETAPES);
        $i = array_search($etape, $cles, true);

        return $i > 0 ? $cles[$i - 1] : null;
    }

    public static function suivante(string $etape): ?string
    {
        $cles = array_keys(self::ETAPES);
        $i = array_search($etape, $cles, true);

        return $cles[$i + 1] ?? null;
    }
}
