<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rapport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'chantier_id', 'rendez_vous_id', 'date_intervention', 'titre', 'travaux', 'constats', 'conseils', 'photos', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date_intervention' => 'date', 'photos' => 'array', 'envoye_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function chantier(): BelongsTo
    {
        return $this->belongsTo(Chantier::class)->withTrashed();
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class)->withTrashed();
    }

    /**
     * Photos choisies, dans l'ordre avant → pendant → après.
     *
     * @return Collection<int, Photo>
     */
    public function lesPhotos(): Collection
    {
        $ids = array_map('intval', (array) $this->photos);
        if (! $ids) {
            return new Collection;
        }

        return Photo::whereIn('id', $ids)->where('client_id', $this->client_id)->get()
            ->sortBy(fn (Photo $p) => array_search($p->moment, array_keys(Photo::MOMENTS), true).'-'.str_pad((string) $p->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    public function reference(): string
    {
        return 'Rapport du '.$this->date_intervention->format('d/m/Y');
    }

    public function libelleCorbeille(): string
    {
        return $this->titre.' ('.$this->date_intervention->format('d/m/Y').')';
    }
}
