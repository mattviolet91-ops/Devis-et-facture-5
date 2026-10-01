<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementEnLigne extends Model
{
    protected $table = 'paiements_en_ligne';

    protected $fillable = ['facture_id', 'order_id', 'montant', 'essai', 'statut', 'transaction', 'paiement_id'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'essai' => 'boolean'];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
