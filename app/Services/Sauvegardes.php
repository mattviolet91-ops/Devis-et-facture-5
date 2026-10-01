<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Sauvegardes : base de données chaque jour, fichiers chaque mois (dossier privé « sauvegardes »).
 */
class Sauvegardes
{
    public const DOSSIER = 'sauvegardes';

    /**
     * @return list<array{nom: string, type: string, taille: int, date: Carbon}>
     */
    public function liste(): array
    {
        $disque = Storage::disk('local');
        $liste = [];
        foreach ($disque->files(self::DOSSIER) as $chemin) {
            $nom = basename($chemin);
            if (! preg_match('/^(base|fichiers)-[\d-]+\.(sql\.gz|zip)$/', $nom, $m)) {
                continue;
            }
            $liste[] = ['nom' => $nom, 'type' => $m[1], 'taille' => $disque->size($chemin), 'date' => Carbon::createFromTimestamp($disque->lastModified($chemin))];
        }
        usort($liste, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $liste;
    }

    public function derniere(?string $type = null): ?Carbon
    {
        foreach ($this->liste() as $sauvegarde) {
            if ($type === null || $sauvegarde['type'] === $type) {
                return $sauvegarde['date'];
            }
        }

        return null;
    }
}
