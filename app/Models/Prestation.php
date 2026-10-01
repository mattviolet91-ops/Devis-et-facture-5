<?php

namespace App\Models;

use App\Support\Montant;
use App\Support\Texte;
use App\Support\Tva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prestation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['categorie', 'nom', 'description', 'unite', 'prix_ht', 'taux_tva'];

    protected function casts(): array
    {
        return ['prix_ht' => 'integer', 'taux_tva' => 'integer', 'utilisations' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Prestation $p) {
            $p->recherche = Texte::pourRecherche($p->categorie.' '.$p->nom.' '.$p->description);
        });
    }

    /**
     * Taux de TVA appliqué (celui de la prestation, sinon le taux par défaut ; 0 en franchise).
     */
    public function tauxEffectif(): int
    {
        if (Tva::estFranchise()) {
            return 0;
        }

        return $this->taux_tva ?? (int) reglage('tva.taux_defaut');
    }

    public function prixAffiche(): string
    {
        return $this->prix_ht === null ? 'Prix à compléter' : Montant::formater($this->prix_ht).' HT / '.$this->unite;
    }

    public function libelleCorbeille(): string
    {
        return $this->nom;
    }

    /**
     * @param  Builder<Prestation>  $query
     */
    public function scopeRecherche(Builder $query, ?string $saisie): void
    {
        foreach (Texte::motsRecherche($saisie) as $mot) {
            $query->where('recherche', 'like', '%'.$mot.'%');
        }
    }

    /**
     * Données envoyées à l'éditeur de devis (ajout rapide).
     *
     * @return array<string, mixed>
     */
    public function pourEditeur(): array
    {
        return [
            'id' => $this->id,
            'categorie' => $this->categorie,
            'nom' => $this->nom,
            'description' => $this->description,
            'unite' => $this->unite,
            'prix_ht' => $this->prix_ht,
            'taux_tva' => $this->tauxEffectif(),
            'prix_affiche' => $this->prixAffiche(),
        ];
    }
}
