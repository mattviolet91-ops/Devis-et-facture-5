<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class LienClient extends Model
{
    protected $table = 'liens_clients';

    protected $fillable = ['document_type', 'document_id', 'jeton_sha256', 'jeton_chiffre'];

    protected function casts(): array
    {
        return ['dernier_acces_at' => 'datetime', 'revoque_at' => 'datetime'];
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Lien du document (le même tant qu'il n'est pas révoqué).
     */
    public static function pour(Model $document): self
    {
        $existant = static::where('document_type', $document->getMorphClass())
            ->where('document_id', $document->getKey())
            ->whereNull('revoque_at')
            ->first();

        if ($existant) {
            return $existant;
        }

        $jeton = Str::random(40);

        return static::create([
            'document_type' => $document->getMorphClass(),
            'document_id' => $document->getKey(),
            'jeton_sha256' => hash('sha256', $jeton),
            'jeton_chiffre' => Crypt::encryptString($jeton),
        ]);
    }

    public static function trouver(string $jeton): ?self
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $jeton)) {
            return null;
        }

        return static::where('jeton_sha256', hash('sha256', $jeton))->whereNull('revoque_at')->first();
    }

    public function jeton(): string
    {
        return Crypt::decryptString($this->jeton_chiffre);
    }

    /**
     * Adresse complète, sur l'adresse réservée aux clients si elle est réglée.
     */
    public function url(string $suite = ''): string
    {
        $base = rtrim((string) (config('app.client_url') ?: config('app.url')), '/');

        return $base.'/c/'.$this->jeton().$suite;
    }
}
