<?php

namespace App\Models;

use App\Models\Concerns\AvecLignes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facture extends Model
{
    use AvecLignes, SoftDeletes;

    public const FACTURE = 'facture';

    public const ACOMPTE = 'acompte';

    public const SITUATION = 'situation';

    public const SOLDE = 'solde';

    public const AVOIR = 'avoir';

    public const TYPES = [
        self::FACTURE => 'Facture',
        self::ACOMPTE => 'Facture d\'acompte',
        self::SITUATION => 'Facture de situation',
        self::SOLDE => 'Facture de solde',
        self::AVOIR => 'Avoir',
    ];

    public const BROUILLON = 'brouillon';

    public const EMISE = 'emise';

    public const PAYEE = 'payee';

    public const ANNULEE = 'annulee';

    public const STATUTS = [
        self::BROUILLON => 'Brouillon',
        self::EMISE => 'Émise',
        self::PAYEE => 'Payée',
        self::ANNULEE => 'Annulée par avoir',
    ];

    protected $attributes = [
        'type' => self::FACTURE,
        'statut' => self::BROUILLON,
        'remise_valeur' => 0,
        'total_ht' => 0,
        'total_remise' => 0,
        'total_tva' => 0,
        'total_ttc' => 0,
        'total_options_ht' => 0,
        'relances' => 0,
    ];

    protected $fillable = [
        'type', 'client_id', 'devis_id', 'chantier_id', 'facture_origine_id', 'objet', 'pourcentage', 'numero_situation',
        'date_prestation', 'delai_paiement_jours', 'remise_type', 'remise_valeur', 'conditions', 'motif', 'relances_auto', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_facture' => 'date',
            'date_prestation' => 'date',
            'date_echeance' => 'date',
            'emise_at' => 'datetime',
            'derniere_relance_at' => 'datetime',
            'relances_auto' => 'boolean',
            'total_ht' => 'integer',
            'total_tva' => 'integer',
            'total_ttc' => 'integer',
            'total_remise' => 'integer',
            'total_options_ht' => 'integer',
            'remise_valeur' => 'integer',
            'pourcentage' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class)->withTrashed();
    }

    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class)->withTrashed();
    }

    public function origine(): BelongsTo
    {
        return $this->belongsTo(Facture::class, 'facture_origine_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class)->orderBy('position');
    }

    public function avoirs(): HasMany
    {
        return $this->hasMany(Facture::class, 'facture_origine_id')->where('type', self::AVOIR)->whereNotNull('numero');
    }

    public function estAvoir(): bool
    {
        return $this->type === self::AVOIR;
    }

    public function estModifiable(): bool
    {
        return $this->statut === self::BROUILLON;
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? 'Facture';
    }

    public function libelleStatut(): string
    {
        if ($this->statut === self::EMISE && $this->estEnRetard()) {
            return 'En retard';
        }

        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function reference(): string
    {
        return $this->numero ?? 'Brouillon n° '.$this->id;
    }

    public function libelleCorbeille(): string
    {
        return $this->libelleType().' '.$this->reference().' — '.$this->client?->nomComplet();
    }

    /**
     * Montant des avoirs émis sur cette facture (TTC, en centimes).
     */
    public function totalAvoirs(): int
    {
        return $this->estAvoir() ? 0 : (int) $this->avoirs()->sum('total_ttc');
    }

    /**
     * Montant déjà encaissé (centimes). Complété par le module Paiements.
     */
    public function totalPaye(): int
    {
        return method_exists($this, 'paiements') ? (int) $this->paiements()->sum('montant') : 0;
    }

    public function resteAPayer(): int
    {
        if ($this->estAvoir() || $this->statut === self::BROUILLON) {
            return 0;
        }

        return max(0, $this->total_ttc - $this->totalAvoirs() - $this->totalPaye());
    }

    public function estEnRetard(): bool
    {
        return $this->statut === self::EMISE && $this->date_echeance !== null
            && $this->date_echeance->endOfDay()->isPast() && $this->resteAPayer() > 0;
    }
}
