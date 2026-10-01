<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Demande extends Model
{
    public const STATUTS = ['nouvelle' => 'Nouvelle', 'traitee' => 'Traitée', 'ecartee' => 'Écartée'];

    protected $attributes = ['statut' => 'nouvelle'];

    protected $fillable = ['source', 'statut', 'nom', 'telephone', 'email', 'ville', 'message', 'message_id', 'client_id', 'recue_at'];

    protected function casts(): array
    {
        return ['recue_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function libelleSource(): string
    {
        return $this->source === 'email' ? 'Email du site' : 'Formulaire';
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}
