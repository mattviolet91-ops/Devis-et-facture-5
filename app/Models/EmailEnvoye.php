<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailEnvoye extends Model
{
    protected $table = 'emails_envoyes';

    protected $fillable = ['document_type', 'document_id', 'client_id', 'destinataire', 'sujet', 'corps', 'piece_jointe', 'modele', 'automatique', 'statut', 'erreur', 'user_id'];

    protected function casts(): array
    {
        return ['automatique' => 'boolean'];
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
