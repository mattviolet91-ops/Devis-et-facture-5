<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeModification extends Model
{
    protected $table = 'demandes_modification';

    protected $fillable = ['devis_id', 'message', 'ip_address'];

    protected function casts(): array
    {
        return ['traitee_at' => 'datetime'];
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
