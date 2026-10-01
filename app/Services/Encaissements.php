<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Support\Alertes;
use App\Support\Journal;
use App\Support\Montant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enregistrement des encaissements : reste à payer, statut « payée », annulation par
 * contre-écriture, chaîne d'empreintes (inaltérabilité).
 */
class Encaissements
{
    /** Plafond légal des paiements en espèces (particulier résident ou professionnel). */
    public const PLAFOND_ESPECES = 100000;

    public function enregistrer(Facture $facture, int $montant, string $mode, string $date, ?string $reference, ?string $notes, ?int $userId): Paiement
    {
        if ($facture->estAvoir() || ! in_array($facture->statut, [Facture::EMISE, Facture::PAYEE], true)) {
            throw ValidationException::withMessages(['montant' => 'On encaisse seulement une facture émise.']);
        }
        if (! array_key_exists($mode, Paiement::MODES)) {
            throw ValidationException::withMessages(['mode' => 'Mode de paiement inconnu.']);
        }
        if ($montant <= 0 || $montant > $facture->resteAPayer()) {
            throw ValidationException::withMessages(['montant' => 'Le montant doit être entre 0,01 € et le reste à payer ('.Montant::formater($facture->resteAPayer()).').']);
        }
        if ($mode === 'especes' && $facture->paiements()->where('mode', 'especes')->sum('montant') + $montant > self::PLAFOND_ESPECES) {
            throw ValidationException::withMessages(['mode' => 'Paiement en espèces limité à 1 000 € par facture (plafond légal). Proposez un virement ou un chèque.']);
        }

        $paiement = $this->ecrire($facture, $montant, $mode, $date, $reference, $notes, null, $userId);
        Journal::ecrire('paiement.enregistre', Montant::formater($montant).' encaissé ('.$paiement->libelleMode().') sur la facture '.$facture->numero, $facture);

        return $paiement;
    }

    /**
     * Annulation d'un encaissement : nouvelle ligne de montant opposé (rien n'est effacé).
     */
    public function annuler(Paiement $paiement, string $motif, ?int $userId): Paiement
    {
        if ($paiement->montant <= 0 || Paiement::where('annule_paiement_id', $paiement->id)->exists()) {
            throw ValidationException::withMessages(['paiement' => 'Cet encaissement est déjà annulé.']);
        }

        $annulation = $this->ecrire($paiement->facture, -$paiement->montant, $paiement->mode, now()->toDateString(), $paiement->reference, 'Annulation : '.$motif, $paiement->id, $userId);
        Journal::ecrire('paiement.annule', 'Encaissement de '.$paiement->montantAffiche().' annulé sur la facture '.$paiement->facture->numero.' : '.$motif, $paiement->facture);

        return $annulation;
    }

    /**
     * Vérifie la chaîne d'empreintes : renvoie l'identifiant de la première ligne altérée, ou null.
     */
    public function premiereAlteration(): ?int
    {
        $precedente = null;
        foreach (Paiement::orderBy('id')->cursor() as $p) {
            $attendue = Paiement::calculerEmpreinte($this->donneesEmpreinte($p), $precedente);
            if (! hash_equals($attendue, $p->empreinte) || $p->empreinte_precedente !== $precedente) {
                return $p->id;
            }
            $precedente = $p->empreinte;
        }

        return null;
    }

    private function ecrire(Facture $facture, int $montant, string $mode, string $date, ?string $reference, ?string $notes, ?int $annule, ?int $userId): Paiement
    {
        return DB::transaction(function () use ($facture, $montant, $mode, $date, $reference, $notes, $annule, $userId) {
            $precedent = Paiement::orderByDesc('id')->lockForUpdate()->first();
            $donnees = [
                'facture_id' => $facture->id, 'montant' => $montant, 'mode' => $mode, 'date_paiement' => $date,
                'reference' => $reference, 'notes' => $notes, 'annule_paiement_id' => $annule, 'user_id' => $userId,
            ];
            $horodatage = now()->format('Y-m-d H:i:s');
            $donnees['empreinte_precedente'] = $precedent?->empreinte;
            $donnees['empreinte'] = Paiement::calculerEmpreinte($donnees + ['horodatage' => $horodatage], $precedent?->empreinte);

            $paiement = new Paiement($donnees);
            $paiement->created_at = $horodatage;
            $paiement->save();

            $this->majStatut($facture->fresh());

            return $paiement;
        });
    }

    private function majStatut(Facture $facture): void
    {
        if ($facture->statut === Facture::ANNULEE) {
            return;
        }

        $statut = $facture->resteAPayer() === 0 ? Facture::PAYEE : Facture::EMISE;
        if ($statut !== $facture->statut) {
            $facture->forceFill(['statut' => $statut])->save();
            if ($statut === Facture::PAYEE) {
                Journal::ecrire('facture.payee', 'Facture '.$facture->numero.' payée', $facture);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesEmpreinte(Paiement $p): array
    {
        return [
            'facture_id' => $p->facture_id, 'montant' => $p->montant, 'mode' => $p->mode,
            'date_paiement' => $p->date_paiement->toDateString(), 'reference' => $p->reference,
            'annule_paiement_id' => $p->annule_paiement_id, 'horodatage' => $p->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public static function prevenirGerant(Facture $facture, string $texte): void
    {
        Alertes::envoyer(User::gerantsActifs()->get(), new AlerteDocument('Paiement reçu', $texte, route('factures.show', $facture)));
    }
}
