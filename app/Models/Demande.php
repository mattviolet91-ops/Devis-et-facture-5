<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Demande extends Model
{
    public const STATUTS = ['nouvelle' => 'Nouvelle', 'traitee' => 'Traitée', 'ecartee' => 'Écartée'];

    protected $attributes = ['statut' => 'nouvelle'];

    protected $fillable = ['source', 'statut', 'nom', 'telephone', 'email', 'adresse', 'code_postal', 'ville', 'message', 'message_id', 'client_id', 'recue_at', 'photos'];

    protected function casts(): array
    {
        return ['recue_at' => 'datetime', 'photos' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function lieu(): string
    {
        return trim(implode(' ', array_filter([$this->adresse ? $this->adresse.',' : null, $this->code_postal, $this->ville])), ' ,');
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
