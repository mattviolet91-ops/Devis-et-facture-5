<?php

namespace App\Models;

use App\Support\Telephone;
use App\Support\Texte;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    public const PARTICULIER = 'particulier';

    public const PROFESSIONNEL = 'professionnel';

    public const CIVILITES = ['M.', 'Mme', 'M. et Mme'];

    protected $fillable = [
        'type', 'civilite', 'nom', 'prenom', 'raison_sociale', 'siret', 'tva_intracom',
        'telephone', 'telephone2', 'email', 'adresse', 'code_postal', 'ville',
        'provenance', 'provenance_detail', 'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            $client->telephone = Telephone::normaliser($client->telephone);
            $client->telephone2 = Telephone::normaliser($client->telephone2);
            $client->email = $client->email ? mb_strtolower(trim($client->email)) : null;
            $client->recherche = Texte::pourRecherche(implode(' ', [
                $client->raison_sociale, $client->nom, $client->prenom, $client->ville,
                $client->code_postal, $client->telephone, $client->telephone2, $client->email,
            ]));
        });
    }

    public function estProfessionnel(): bool
    {
        return $this->type === self::PROFESSIONNEL;
    }

    /**
     * « Mme Sophie Martin » ou la raison sociale d'un professionnel.
     */
    public function nomComplet(): string
    {
        if ($this->estProfessionnel() && $this->raison_sociale) {
            return $this->raison_sociale;
        }

        return trim(implode(' ', array_filter([$this->civilite, $this->prenom, $this->nom]))) ?: 'Client sans nom';
    }

    /**
     * Pour un professionnel : la personne à contacter.
     */
    public function contact(): ?string
    {
        if (! $this->estProfessionnel()) {
            return null;
        }

        return trim(implode(' ', array_filter([$this->civilite, $this->prenom, $this->nom]))) ?: null;
    }

    public function adresseComplete(): string
    {
        return trim(implode(', ', array_filter([$this->adresse, trim($this->code_postal.' '.$this->ville)])));
    }

    public function libelleCorbeille(): string
    {
        return $this->nomComplet();
    }

    public function chantiers(): HasMany
    {
        return $this->hasMany(Chantier::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(NoteClient::class)->latest()->latest('id');
    }

    public function piecesJointes(): MorphMany
    {
        return $this->morphMany(PieceJointe::class, 'attachable')->latest();
    }

    /**
     * Recherche sans tenir compte des accents ni des majuscules.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeRecherche(Builder $query, ?string $saisie): void
    {
        foreach (Texte::motsRecherche($saisie) as $mot) {
            $chiffres = preg_replace('/\D/', '', $mot);
            $query->where(function (Builder $q) use ($mot, $chiffres) {
                $q->where('recherche', 'like', '%'.$mot.'%');
                // « 06 12 34 » doit trouver « 0612345678 ».
                if ($chiffres !== '' && strlen($chiffres) >= 2) {
                    $q->orWhere('telephone', 'like', '%'.$chiffres.'%');
                }
            });
        }
    }

    /**
     * Clients qui ont le même téléphone ou le même email (alerte de doublon).
     *
     * @return Collection<int, Client>
     */
    public static function doublons(?string $telephone, ?string $email, ?int $sauf = null): Collection
    {
        $telephone = Telephone::normaliser($telephone);
        $email = $email ? mb_strtolower(trim($email)) : null;

        if (! $telephone && ! $email) {
            return collect();
        }

        return static::query()
            ->when($sauf, fn ($q) => $q->whereKeyNot($sauf))
            ->where(function ($q) use ($telephone, $email) {
                if ($telephone) {
                    $q->orWhere('telephone', $telephone)->orWhere('telephone2', $telephone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->limit(5)
            ->get();
    }
}
