<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chantier extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES_TOITURE = [
        'Tuiles mécaniques', 'Tuiles plates', 'Tuiles canal', 'Ardoises naturelles', 'Ardoises fibrociment',
        'Zinc', 'Bac acier', 'Shingle', 'Toit-terrasse', 'Autre',
    ];

    public const ACCES = [
        'Facile (plain-pied)', 'Échelle suffisante', 'Échafaudage nécessaire', 'Nacelle nécessaire', 'Accès difficile',
    ];

    protected $fillable = ['client_id', 'libelle', 'adresse', 'code_postal', 'ville', 'type_toiture', 'surface', 'pente', 'acces', 'notes'];

    protected function casts(): array
    {
        return ['surface' => 'decimal:2', 'pente' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function piecesJointes(): MorphMany
    {
        return $this->morphMany(PieceJointe::class, 'attachable')->latest();
    }

    public function titre(): string
    {
        return $this->libelle ?: ($this->adresseComplete() ?: 'Chantier');
    }

    public function adresseComplete(): string
    {
        return trim(implode(', ', array_filter([$this->adresse, trim($this->code_postal.' '.$this->ville)])));
    }

    public function libelleCorbeille(): string
    {
        return $this->titre().' ('.$this->client?->nomComplet().')';
    }

    /**
     * Lien d'itinéraire (ouvre l'application de cartes du téléphone).
     */
    public function lienItineraire(): ?string
    {
        $adresse = $this->adresseComplete();

        return $adresse !== '' ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($adresse) : null;
    }

    public function surfaceAffichee(): ?string
    {
        if ($this->surface === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $this->surface, 2, ',', ' '), '0'), ',').' m²';
    }
}
