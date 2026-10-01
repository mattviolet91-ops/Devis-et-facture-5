<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class RendezVous extends Model
{
    use SoftDeletes;

    protected $table = 'rendez_vous';

    protected $attributes = ['type' => 'rdv', 'fait' => false];

    protected $fillable = [
        'type', 'titre', 'debut', 'fin', 'journee_entiere', 'lieu', 'client_id', 'chantier_id', 'devis_id',
        'user_id', 'notes', 'fait', 'rappel_client_jours', 'created_by',
    ];

    protected function casts(): array
    {
        return ['debut' => 'datetime', 'fin' => 'datetime', 'journee_entiere' => 'boolean', 'fait' => 'boolean', 'rappels_envoyes' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class)->withTrashed();
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estChantier(): bool
    {
        return $this->type === 'chantier';
    }

    /**
     * Adresse utilisée pour l'itinéraire et la météo.
     */
    public function adresse(): ?string
    {
        return $this->lieu ?: ($this->chantier?->adresseComplete() ?: $this->client?->adresseComplete()) ?: null;
    }

    public function lienItineraire(): ?string
    {
        $adresse = $this->adresse();

        return $adresse ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($adresse) : null;
    }

    public function horaire(): string
    {
        if ($this->journee_entiere || $this->debut->toDateString() !== $this->fin->toDateString()) {
            return $this->debut->toDateString() === $this->fin->toDateString()
                ? 'Toute la journée'
                : 'Du '.$this->debut->format('d/m').' au '.$this->fin->format('d/m');
        }

        return $this->debut->format('H\hi').' – '.$this->fin->format('H\hi');
    }

    public function libelleCorbeille(): string
    {
        return $this->titre.' ('.$this->debut->format('d/m/Y').')';
    }

    /**
     * Rendez-vous qui touchent la période (y compris les chantiers sur plusieurs jours).
     *
     * @param  Builder<RendezVous>  $query
     */
    public function scopeEntre(Builder $query, Carbon $debut, Carbon $fin): void
    {
        $query->where('debut', '<=', $fin)->where('fin', '>=', $debut);
    }

    public function rappelEnvoye(string $cle): bool
    {
        return in_array($cle, (array) $this->rappels_envoyes, true);
    }

    public function noterRappel(string $cle): void
    {
        $this->forceFill(['rappels_envoyes' => array_values(array_unique([...(array) $this->rappels_envoyes, $cle]))])->saveQuietly();
    }
}
