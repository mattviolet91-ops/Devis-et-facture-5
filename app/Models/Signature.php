<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    protected $fillable = ['devis_id', 'nom', 'image_chemin', 'ip_address', 'user_agent', 'sur_place', 'execution_immediate', 'pdf_chemin', 'pdf_sha256', 'signe_at'];

    protected function casts(): array
    {
        return ['signe_at' => 'datetime', 'sur_place' => 'boolean', 'execution_immediate' => 'boolean'];
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
