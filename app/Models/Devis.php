<?php

namespace App\Models;

use App\Models\Concerns\AvecLignes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Devis extends Model
{
    use AvecLignes, SoftDeletes;

    protected $table = 'devis';

    public const BROUILLON = 'brouillon';

    public const ENVOYE = 'envoye';

    public const ACCEPTE = 'accepte';

    public const REFUSE = 'refuse';

    public const EXPIRE = 'expire';

    public const REMPLACE = 'remplace';

    public const STATUTS = [
        self::BROUILLON => 'Brouillon',
        self::ENVOYE => 'Envoyé',
        self::ACCEPTE => 'Accepté',
        self::REFUSE => 'Refusé',
        self::EXPIRE => 'Expiré',
        self::REMPLACE => 'Remplacé',
    ];

    /** Valeurs par défaut, connues dès la création (avant relecture en base). */
    protected $attributes = [
        'statut' => self::BROUILLON,
        'version' => 1,
        'remise_valeur' => 0,
        'total_ht' => 0,
        'total_remise' => 0,
        'total_tva' => 0,
        'total_ttc' => 0,
        'total_options_ht' => 0,
    ];

    protected $fillable = [
        'client_id', 'chantier_id', 'objet', 'date_devis', 'validite_jours', 'acompte_pourcentage',
        'date_debut_travaux', 'duree_travaux', 'dechets_estimation', 'remise_type', 'remise_valeur',
        'conditions', 'hors_etablissement', 'urgence', 'created_by', 'version', 'devis_origine_id',
    ];

    protected function casts(): array
    {
        return [
            'date_devis' => 'date',
            'date_debut_travaux' => 'date',
            'envoye_at' => 'datetime',
            'derniere_relance_at' => 'datetime',
            'relances_auto' => 'boolean',
            'accepte_at' => 'datetime',
            'refuse_at' => 'datetime',
            'pdf_fige_at' => 'datetime',
            'hors_etablissement' => 'boolean',
            'urgence' => 'boolean',
            'total_ht' => 'integer',
            'total_remise' => 'integer',
            'total_tva' => 'integer',
            'total_ttc' => 'integer',
            'total_options_ht' => 'integer',
            'remise_valeur' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class)->withTrashed();
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDevis::class)->orderBy('position');
    }

    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class)->latestOfMany();
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function demandesModification(): HasMany
    {
        return $this->hasMany(DemandeModification::class)->latest();
    }

    /**
     * Le client peut-il signer en ligne ? (devis envoyé et encore valable)
     */
    public function peutEtreSigne(): bool
    {
        return $this->statut === self::ENVOYE && ! ($this->dateValidite()?->endOfDay()->isPast() ?? false);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function origine(): BelongsTo
    {
        return $this->belongsTo(Devis::class, 'devis_origine_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estModifiable(): bool
    {
        // Un devis envoyé ne se modifie plus : on fait une nouvelle version.
        return $this->statut === self::BROUILLON;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function reference(): string
    {
        return $this->numero ?? 'Brouillon n° '.$this->id;
    }

    public function dateValidite(): ?Carbon
    {
        $depart = $this->date_devis ?? $this->envoye_at;

        return $depart ? Carbon::parse($depart)->addDays($this->validite_jours) : null;
    }

    public function libelleCorbeille(): string
    {
        return $this->reference().' — '.$this->client?->nomComplet();
    }

    /**
     * @param  Builder<Devis>  $query
     */
    public function scopeStatut(Builder $query, ?string $statut): void
    {
        if ($statut && isset(self::STATUTS[$statut])) {
            $query->where('statut', $statut);
        }
    }
}
