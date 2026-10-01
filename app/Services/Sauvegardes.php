<?php

namespace App\Services;

use App\Support\Reglages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegardes : base de données chaque jour, fichiers chaque mois (dossier privé « sauvegardes »).
 *
 * La base est exportée en PHP (lignes JSON compressées), sans outil extérieur : cela marche
 * avec SQLite et MariaDB, sur un hébergement mutualisé. La restauration vide les tables
 * puis remet les lignes, en un seul bloc.
 */
class Sauvegardes
{
    public const DOSSIER = 'sauvegardes';

    /** Sauvegardes gardées : 30 de la base, 12 des fichiers. */
    public const GARDER = ['base' => 30, 'fichiers' => 12];

    /** Tables techniques non sauvegardées (sessions, cache, files d'attente). */
    public const IGNOREES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /**
     * @return list<array{nom: string, type: string, taille: int, date: Carbon}>
     */
    public function liste(): array
    {
        $disque = Storage::disk('local');
        $liste = [];
        foreach ($disque->files(self::DOSSIER) as $chemin) {
            $nom = basename($chemin);
            if (! preg_match('/^(base|fichiers)-[\d-]+\.(json\.gz|zip)$/', $nom, $m)) {
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

    public static function nomValide(string $nom): bool
    {
        return (bool) preg_match('/^(base|fichiers)-[\d-]+\.(json\.gz|zip)$/', $nom);
    }

    /**
     * Exporte toute la base (une ligne JSON par enregistrement), compressée.
     */
    public function sauvegarderBase(): string
    {
        $tables = array_values(array_diff($this->tables(), self::IGNOREES));
        $chemin = self::DOSSIER.'/base-'.now()->format('Y-m-d-His').'.json.gz';
        $complet = Storage::disk('local')->path($chemin);
        @mkdir(dirname($complet), 0770, true);

        $gz = gzopen($complet.'.partiel', 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Impossible d\'écrire la sauvegarde.');
        }
        gzwrite($gz, json_encode(['application' => 'sauvegarde', 'version' => 1, 'date' => now()->toIso8601String(), 'migration' => $this->derniereMigration(), 'tables' => $tables])."\n");
        foreach ($tables as $table) {
            $cle = Schema::hasColumn($table, 'id') ? 'id' : null;
            $requete = DB::table($table);
            $ecrire = function ($ligne) use ($gz, $table) {
                gzwrite($gz, json_encode(['t' => $table, 'l' => (array) $ligne], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
            };
            $cle ? $requete->orderBy($cle)->chunkById(500, fn ($lignes) => $lignes->each($ecrire), $cle) : $requete->get()->each($ecrire);
        }
        gzclose($gz);
        rename($complet.'.partiel', $complet);

        $this->nettoyer('base');

        return $chemin;
    }

    /**
     * Archive les fichiers (photos, PDF figés, logo, attestation…), sans les sauvegardes elles-mêmes.
     */
    public function sauvegarderFichiers(): string
    {
        $disque = Storage::disk('local');
        $chemin = self::DOSSIER.'/fichiers-'.now()->format('Y-m-d-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($disque->path($chemin), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l\'archive des fichiers.');
        }
        foreach ($disque->allFiles() as $fichier) {
            if (! str_starts_with($fichier, self::DOSSIER.'/') && ! str_starts_with(basename($fichier), '.')) {
                $zip->addFile($disque->path($fichier), $fichier);
            }
        }
        $zip->addFromString('LISEZ-MOI.txt', "Fichiers de l'application au ".now()->format('d/m/Y').".\nÀ remettre dans storage/app/private/.\n");
        $zip->close();

        $this->nettoyer('fichiers');

        return $chemin;
    }

    /**
     * Remet une sauvegarde de la base : TOUTES les données actuelles sont remplacées.
     */
    public function restaurerBase(string $chemin): int
    {
        $gz = gzopen($chemin, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Sauvegarde illisible.');
        }
        $entete = json_decode((string) gzgets($gz), true);
        if (($entete['application'] ?? null) !== 'sauvegarde') {
            gzclose($gz);
            throw new RuntimeException('Ce fichier n\'est pas une sauvegarde de l\'application.');
        }

        // Tout se fait d'un bloc : en cas d'erreur, rien n'est changé.
        $total = 0;
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($gz, &$total) {
                // SQLite : contrôles des liens repoussés à la fin du bloc (l'ordre des tables n'importe plus).
                if (DB::getDriverName() === 'sqlite') {
                    DB::statement('PRAGMA defer_foreign_keys = ON');
                }
                foreach (array_diff($this->tables(), self::IGNOREES) as $table) {
                    DB::table($table)->delete();
                }

                $colonnes = [];
                $paquet = [];
                $table = null;
                while (($ligne = gzgets($gz)) !== false) {
                    $donnees = json_decode($ligne, true);
                    if (! is_array($donnees) || ! isset($donnees['t'], $donnees['l']) || in_array($donnees['t'], self::IGNOREES, true) || ! Schema::hasTable($donnees['t'])) {
                        continue;
                    }
                    if ($table !== null && ($donnees['t'] !== $table || count($paquet) >= 200)) {
                        DB::table($table)->insert($paquet);
                        $paquet = [];
                    }
                    $table = $donnees['t'];
                    // Colonnes disparues depuis la sauvegarde : ignorées.
                    $colonnes[$table] ??= array_flip(Schema::getColumnListing($table));
                    $paquet[] = array_intersect_key($donnees['l'], $colonnes[$table]);
                    $total++;
                }
                if ($table !== null && $paquet) {
                    DB::table($table)->insert($paquet);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
            gzclose($gz);
        }

        app(Reglages::class)->vider();

        return $total;
    }

    private function nettoyer(string $type): void
    {
        $anciennes = array_slice(array_values(array_filter($this->liste(), fn ($s) => $s['type'] === $type)), self::GARDER[$type]);
        foreach ($anciennes as $sauvegarde) {
            Storage::disk('local')->delete(self::DOSSIER.'/'.$sauvegarde['nom']);
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return array_map(fn ($t) => is_array($t) ? $t['name'] : (string) $t, Schema::getTables());
    }

    private function derniereMigration(): ?string
    {
        return Schema::hasTable('migrations') ? DB::table('migrations')->orderByDesc('id')->value('migration') : null;
    }
}
