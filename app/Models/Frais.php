<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Frais d'un chantier (matériaux, location…) rattachés à une facture, avec la photo du ticket.
 */
class Frais extends Model
{
    public const CATEGORIES = [
        'materiaux' => 'Matériaux',
        'location' => 'Location (échafaudage, nacelle…)',
        'sous_traitance' => 'Sous-traitance',
        'deplacement' => 'Déplacement, carburant',
        'dechets' => 'Déchets, déchetterie',
        'autre' => 'Autre',
    ];

    protected $table = 'frais';

    protected $attributes = ['categorie' => 'materiaux'];

    protected $fillable = ['facture_id', 'date_frais', 'libelle', 'categorie', 'montant_ttc', 'justificatif', 'user_id'];

    protected function casts(): array
    {
        return ['date_frais' => 'date', 'montant_ttc' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Frais $frais) => $frais->justificatif && Storage::disk('local')->delete($frais->justificatif));
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function libelleCategorie(): string
    {
        return self::CATEGORIES[$this->categorie] ?? $this->categorie;
    }
}
