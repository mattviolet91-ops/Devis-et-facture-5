<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers des réglages (logo, icône, attestation d'assurance), rangés hors du
 * dossier public (storage/app/private/reglages) et servis par l'application.
 */
class FichiersReglages
{
    public const DOSSIER = 'reglages';

    public const TAILLES_ICONE = [180, 192, 512];

    public static function disque(): Filesystem
    {
        return Storage::disk('local');
    }

    public static function enregistrer(string $cle, UploadedFile $fichier): string
    {
        self::supprimer($cle);

        $nom = str_replace('.', '-', $cle).'.'.strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension());
        $chemin = $fichier->storeAs(self::DOSSIER, $nom, 'local');

        app(Reglages::class)->set($cle, $chemin);

        if ($cle === 'apparence.icone') {
            self::genererIcones($chemin);
        }

        return $chemin;
    }

    public static function supprimer(string $cle): void
    {
        $chemin = (string) reglage($cle);
        if ($chemin !== '') {
            self::disque()->delete($chemin);
        }

        if ($cle === 'apparence.icone') {
            foreach (self::TAILLES_ICONE as $taille) {
                self::disque()->delete(self::cheminIcone($taille));
            }
        }

        app(Reglages::class)->set($cle, '');
    }

    public static function cheminIcone(int $taille): string
    {
        return self::DOSSIER."/icone-{$taille}.png";
    }

    public static function aIcone(): bool
    {
        return reglage('apparence.icone') !== '' && self::disque()->exists(self::cheminIcone(512));
    }

    /**
     * Redimensionne l'icône en carrés PNG (180, 192 et 512 pixels).
     */
    private static function genererIcones(string $chemin): void
    {
        $source = @imagecreatefromstring(self::disque()->get($chemin));
        if (! $source) {
            return;
        }

        $cote = min(imagesx($source), imagesy($source));

        foreach (self::TAILLES_ICONE as $taille) {
            $image = imagecreatetruecolor($taille, $taille);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, 0, 0, $taille, $taille, $cote, $cote);

            ob_start();
            imagepng($image, null, 9);
            self::disque()->put(self::cheminIcone($taille), (string) ob_get_clean());
        }
    }
}
