<?php

namespace App\Services;

use App\Models\Devis;
use App\Models\Facture;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pages de photos « avant / après » jointes en annexe des PDF.
 * Pour un devis ou une facture : les photos du chantier cochées « Mettre dans les devis et factures ».
 */
class PhotosAnnexe
{
    public const PAR_PAGE = 6;

    /**
     * @return list<string> HTML de chaque page d'annexe photos.
     */
    public static function pour(Model $document): array
    {
        if (! $document instanceof Devis && ! $document instanceof Facture) {
            return [];
        }

        $photos = Photo::where('client_id', $document->client_id)
            ->where('dans_documents', true)
            ->when($document->chantier_id, fn ($q) => $q->where(fn ($q) => $q->where('chantier_id', $document->chantier_id)->orWhereNull('chantier_id')))
            ->orderByRaw("case moment when 'probleme' then 0 when 'avant' then 1 when 'pendant' then 2 when 'reparation' then 3 else 4 end")
            ->orderBy('id')
            ->get();

        return self::pages($photos, 'Annexe : photos');
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return list<string>
     */
    public static function pages(Collection $photos, string $titre): array
    {
        $photos = $photos->filter(fn (Photo $p) => is_file($p->cheminComplet()))->values();
        if ($photos->isEmpty()) {
            return [];
        }

        return $photos->chunk(self::PAR_PAGE)
            ->map(fn (Collection $groupe) => view('pdf.photos', ['titre' => $titre, 'photos' => $groupe->values()])->render())
            ->values()
            ->all();
    }
}
