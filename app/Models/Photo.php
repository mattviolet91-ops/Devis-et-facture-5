<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    public const MOMENTS = ['avant' => 'Avant', 'pendant' => 'Pendant', 'apres' => 'Après', 'probleme' => 'Problème', 'reparation' => 'Réparation'];

    protected $attributes = ['moment' => 'avant', 'dans_documents' => false];

    protected $fillable = [
        'client_id', 'chantier_id', 'rendez_vous_id', 'moment', 'legende', 'chemin', 'miniature',
        'largeur', 'hauteur', 'taille', 'dans_documents', 'user_id', 'chemin_original',
    ];

    protected function casts(): array
    {
        return ['dans_documents' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (Photo $photo) => Storage::disk('local')->delete(array_filter([$photo->chemin, $photo->miniature, $photo->chemin_original])));
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

    public function libelleMoment(): string
    {
        return self::MOMENTS[$this->moment] ?? $this->moment;
    }

    public function texteAlternatif(): string
    {
        return trim('Photo '.mb_strtolower($this->libelleMoment()).' travaux'.($this->legende ? ' : '.$this->legende : ''));
    }

    public function cheminComplet(bool $miniature = false): string
    {
        return Storage::disk('local')->path($miniature ? $this->miniature : $this->chemin);
    }
}
