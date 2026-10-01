<?php

namespace App\Support;

use App\Models\User;

/**
 * « Astuce du jour » de l'accueil : chaque jour un autre « Bon à savoir » du guide.
 */
class Astuces
{
    /**
     * @return array{texte: string, titre: string, rubrique: string}
     */
    public static function duJour(User $user): array
    {
        $rubriques = Guide::pour($user);
        $cles = array_keys($rubriques);
        $cle = $cles[(int) now()->dayOfYear % count($cles)];

        return ['texte' => $rubriques[$cle]['bon_a_savoir'], 'titre' => $rubriques[$cle]['titre'], 'rubrique' => $cle];
    }
}
