<?php

namespace App\Models;

use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Paiement extends Model
{
    public const UPDATED_AT = null;

    public const MODES = [
        'virement' => 'Virement',
        'carte' => 'Carte bancaire',
        'cheque' => 'Chèque',
        'especes' => 'Espèces',
        'carte_en_ligne' => 'Carte en ligne',
    ];

    protected $fillable = ['facture_id', 'montant', 'mode', 'date_paiement', 'reference', 'notes', 'annule_paiement_id', 'user_id', 'empreinte', 'empreinte_precedente'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'date_paiement' => 'date'];
    }

    protected static function booted(): void
    {
        // Inaltérabilité : un encaissement enregistré ne change plus et ne disparaît pas.
        static::updating(fn () => throw new LogicException('Un paiement enregistré ne se modifie pas : faites une annulation.'));
        static::deleting(fn () => throw new LogicException('Un paiement enregistré ne se supprime pas : faites une annulation.'));
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libelleMode(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    public function montantAffiche(): string
    {
        return Montant::formater($this->montant);
    }

    /**
     * Empreinte de la ligne, chaînée à la précédente.
     *
     * @param  array<string, mixed>  $donnees
     */
    public static function calculerEmpreinte(array $donnees, ?string $precedente): string
    {
        return hash('sha256', implode('|', [
            $precedente ?? '',
            $donnees['facture_id'], $donnees['montant'], $donnees['mode'], $donnees['date_paiement'],
            $donnees['reference'] ?? '', $donnees['annule_paiement_id'] ?? '', $donnees['horodatage'],
        ]));
    }
}
