<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Corbeille : liste des types d'éléments supprimés « en douceur »
 * (SoftDeletes) que l'on peut restaurer pendant 30 jours.
 *
 * Chaque module ajoute ses modèles avec Corbeille::enregistrer().
 * Les factures émises ne doivent JAMAIS y figurer (conservation 10 ans).
 */
class Corbeille
{
    public const JOURS_CONSERVATION = 30;

    /** @var array<string, array{classe: class-string<Model>, libelle: string}> */
    private static array $types = [];

    /**
     * @param  class-string<Model>  $classe
     */
    public static function enregistrer(string $cle, string $classe, string $libelle): void
    {
        self::$types[$cle] = ['classe' => $classe, 'libelle' => $libelle];
    }

    public static function retirer(string $cle): void
    {
        unset(self::$types[$cle]);
    }

    /**
     * @return array<string, array{classe: class-string<Model>, libelle: string}>
     */
    public static function types(): array
    {
        return self::$types;
    }

    /**
     * @return array{classe: class-string<Model>, libelle: string}|null
     */
    public static function type(string $cle): ?array
    {
        return self::$types[$cle] ?? null;
    }

    /**
     * Éléments dans la corbeille, du plus récent au plus ancien.
     *
     * @return Collection<int, array{cle: string, type: string, modele: Model}>
     */
    public static function elements(): Collection
    {
        return collect(self::$types)
            ->flatMap(fn (array $type, string $cle) => $type['classe']::onlyTrashed()->get()
                ->map(fn (Model $modele) => ['cle' => $cle, 'type' => $type['libelle'], 'modele' => $modele]))
            ->sortByDesc(fn (array $e) => $e['modele']->deleted_at)
            ->values();
    }

    /**
     * Supprime définitivement ce qui est dans la corbeille depuis plus de 30 jours.
     */
    public static function purger(): int
    {
        $limite = now()->subDays(self::JOURS_CONSERVATION);
        $total = 0;

        foreach (self::$types as $type) {
            $type['classe']::onlyTrashed()
                ->where('deleted_at', '<', $limite)
                ->each(function (Model $modele) use (&$total) {
                    $modele->forceDelete();
                    $total++;
                });
        }

        return $total;
    }

    public static function libelle(Model $modele): string
    {
        if (method_exists($modele, 'libelleCorbeille')) {
            return $modele->libelleCorbeille();
        }

        return '#'.$modele->getKey();
    }
}
