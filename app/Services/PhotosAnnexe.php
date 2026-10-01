<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Pages de photos « avant / après » jointes en annexe des PDF.
 * (Rempli par le module Photos.)
 */
class PhotosAnnexe
{
    /**
     * @return list<string> HTML de chaque page d'annexe photos.
     */
    public static function pour(Model $document): array
    {
        return [];
    }
}
