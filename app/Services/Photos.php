<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Enregistre une photo de chantier : redressée, réduite (1600 px), ré-encodée en JPEG.
 * Le ré-encodage retire les données EXIF (position GPS du téléphone, etc.).
 */
class Photos
{
    public const COTE_MAX = 1600;

    public const COTE_MINIATURE = 400;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(UploadedFile $fichier, array $attributs): Photo
    {
        return Photo::create($attributs + $this->ranger($fichier, 'photos/'.$attributs['client_id']));
    }

    /**
     * Range une image déjà ouverte (photos d'exemple de la démonstration).
     *
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrerImage(\GdImage $image, array $attributs): Photo
    {
        return Photo::create($attributs + $this->rangerImage($image, 'photos/'.$attributs['client_id']));
    }

    /**
     * Prépare une photo (réduite + miniature) dans un dossier, sans créer de fiche.
     *
     * @return array{chemin: string, miniature: string, largeur: int, hauteur: int, taille: int}
     */
    public function ranger(UploadedFile $fichier, string $dossier): array
    {
        $image = $this->ouvrir($fichier->getRealPath(), (string) $fichier->getMimeType());

        try {
            return $this->rangerImage($image, $dossier);
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Remplace la photo par sa version annotée ; l'originale est gardée une seule fois.
     */
    public function annoter(Photo $photo, UploadedFile $fichier): Photo
    {
        $ancienne = $photo->only(['chemin', 'miniature']);
        $nouvelle = $this->ranger($fichier, dirname($photo->chemin));

        $photo->forceFill($nouvelle + ['original' => $photo->original ?? $ancienne['chemin']])->save();
        Storage::disk('local')->delete(array_filter([$ancienne['miniature'], $ancienne['chemin'] !== $photo->original ? $ancienne['chemin'] : null]));

        return $photo;
    }

    /**
     * Revient à la photo d'origine (sans les traits).
     */
    public function retablir(Photo $photo): Photo
    {
        if (! $photo->original || ! Storage::disk('local')->exists($photo->original)) {
            return $photo;
        }

        $image = $this->ouvrir(Storage::disk('local')->path($photo->original), 'image/jpeg');
        $ancienne = $photo->only(['chemin', 'miniature']);
        try {
            $nouvelle = $this->rangerImage($image, dirname($photo->chemin));
        } finally {
            imagedestroy($image);
        }
        Storage::disk('local')->delete([$photo->original, $ancienne['chemin'], $ancienne['miniature']]);
        $photo->forceFill($nouvelle + ['original' => null])->save();

        return $photo;
    }

    /**
     * @return array{chemin: string, miniature: string, largeur: int, hauteur: int, taille: int}
     */
    private function rangerImage(\GdImage $image, string $dossier): array
    {
        $nom = (string) Str::uuid();
        [$largeur, $hauteur, $contenu] = $this->reduire($image, self::COTE_MAX, 82);
        [, , $miniature] = $this->reduire($image, self::COTE_MINIATURE, 75);
        Storage::disk('local')->put($chemin = $dossier.'/'.$nom.'.jpg', $contenu);
        Storage::disk('local')->put($cheminMiniature = $dossier.'/'.$nom.'-mini.jpg', $miniature);

        return ['chemin' => $chemin, 'miniature' => $cheminMiniature, 'largeur' => $largeur, 'hauteur' => $hauteur, 'taille' => strlen($contenu)];
    }

    private function ouvrir(string $chemin, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($chemin),
            'image/png' => @imagecreatefrompng($chemin),
            'image/webp' => @imagecreatefromwebp($chemin),
            default => false,
        };
        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Photo illisible.');
        }

        // Redresse la photo selon l'orientation enregistrée par le téléphone.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($chemin)['Orientation'] ?? 1);
            $angle = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $tournee = imagerotate($image, $angle, 0);
                imagedestroy($image);
                $image = $tournee;
            }
        }

        return $image;
    }

    /**
     * @return array{int, int, string}
     */
    private function reduire(\GdImage $image, int $coteMax, int $qualite): array
    {
        $l = imagesx($image);
        $h = imagesy($image);
        $ratio = min(1, $coteMax / max($l, $h));
        $nl = max(1, (int) round($l * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $copie = imagecreatetruecolor($nl, $nh);
        imagefill($copie, 0, 0, imagecolorallocate($copie, 255, 255, 255)); // fond blanc pour les PNG transparents
        imagecopyresampled($copie, $image, 0, 0, 0, 0, $nl, $nh, $l, $h);

        ob_start();
        imagejpeg($copie, null, $qualite);
        $contenu = (string) ob_get_clean();
        imagedestroy($copie);

        return [$nl, $nh, $contenu];
    }
}
