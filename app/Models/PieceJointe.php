<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    protected $fillable = ['attachable_type', 'attachable_id', 'nom', 'chemin', 'mime', 'taille', 'user_id'];

    protected static function booted(): void
    {
        static::deleted(fn (PieceJointe $piece) => Storage::disk('local')->delete($piece->chemin));
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tailleAffichee(): string
    {
        return $this->taille >= 1048576
            ? number_format($this->taille / 1048576, 1, ',', '').' Mo'
            : max(1, (int) round($this->taille / 1024)).' Ko';
    }

    public function estImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
