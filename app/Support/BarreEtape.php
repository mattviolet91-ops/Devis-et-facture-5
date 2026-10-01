<?php

namespace App\Support;

use App\Models\Devis;
use App\Models\Facture;
use App\Models\User;

/**
 * Actions de la barre fixe en bas des devis et factures, selon l'étape.
 */
class BarreEtape
{
    /**
     * @return list<array<string, string>>
     */
    public static function devis(Devis $devis, User $user): array
    {
        if ($devis->statut === Devis::BROUILLON) {
            return [
                ['libelle' => 'Envoyer', 'url' => route('envoi.create', ['devis', $devis->id])],
                ['libelle' => 'Modifier', 'url' => route('devis.edit', $devis)],
            ];
        }
        if ($devis->peutEtreSigne()) {
            return [
                ['libelle' => 'Faire signer', 'url' => route('devis.signer', $devis)],
                ['libelle' => 'Relancer', 'url' => route('envoi.create', ['devis', $devis->id, 'modele' => 'relance_devis'])],
                ['libelle' => 'Accepté', 'post' => route('devis.accepter', $devis), 'confirmer' => 'Le client a accepté ce devis ?'],
            ];
        }
        if ($devis->statut === Devis::ACCEPTE) {
            $actions = [];
            if ($user->estGerant()) {
                $actions[] = ['libelle' => 'Facturer', 'url' => '#facturer'];
            }
            if (! $devis->rendezVous()->where('type', 'chantier')->exists()) {
                $actions[] = ['libelle' => 'Planifier', 'url' => route('planning.create', ['devis' => $devis->id])];
            }

            return $actions;
        }

        return [];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function facture(Facture $facture, User $user): array
    {
        if (! $user->estGerant()) {
            return [];
        }
        if ($facture->statut === Facture::BROUILLON) {
            return [
                ['libelle' => 'Émettre', 'post' => route('factures.emettre', $facture), 'confirmer' => 'La facture recevra son numéro et ne pourra plus être modifiée. Continuer ?'],
                ['libelle' => 'Modifier', 'url' => route('factures.edit', $facture)],
            ];
        }
        if (! $facture->estAvoir() && $facture->statut === Facture::EMISE && $facture->resteAPayer() > 0) {
            return [
                ['libelle' => 'Encaisser', 'url' => '#encaisser'],
                ['libelle' => $facture->estEnRetard() ? 'Relancer' : 'Envoyer', 'url' => route('envoi.create', ['facture', $facture->id])],
            ];
        }

        return [];
    }
}
