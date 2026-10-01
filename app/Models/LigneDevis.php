<?php

namespace App\Models;

use App\Support\Montant;
use App\Support\Quantite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneDevis extends Model
{
    protected $table = 'lignes_devis';

    protected $fillable = [
        'devis_id', 'position', 'type', 'designation', 'description', 'quantite', 'unite',
        'prix_unitaire_ht', 'taux_tva', 'option', 'prestation_id', 'total_ht',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire_ht' => 'integer',
            'taux_tva' => 'integer',
            'option' => 'boolean',
            'total_ht' => 'integer',
        ];
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    public function quantiteAffichee(): string
    {
        return Quantite::formater($this->quantite);
    }

    public function prixAffiche(): string
    {
        return Montant::formater($this->prix_unitaire_ht);
    }

    public function totalAffiche(): string
    {
        return Montant::formater($this->total_ht);
    }
}
