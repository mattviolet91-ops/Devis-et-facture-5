<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Prestation;
use App\Support\Journal;
use App\Support\Montant;
use App\Support\Tva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Factures : création (depuis un devis accepté ou directe), acompte, situation, solde,
 * avoir, émission (numéro attribué à ce moment-là, puis document figé).
 */
class GestionFactures
{
    public function __construct(private Numerotation $numerotation, private PdfFacture $pdf) {}

    public function creerVide(Client $client, int $userId): Facture
    {
        return Facture::create([
            'client_id' => $client->id,
            'delai_paiement_jours' => (int) reglage('documents.delai_paiement_jours'),
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  int|null  $pourcentage  Centièmes de % (acompte : part du devis ; situation : avancement cumulé).
     */
    public function depuisDevis(Devis $devis, string $type, ?int $pourcentage, int $userId): Facture
    {
        if ($devis->statut !== Devis::ACCEPTE) {
            throw ValidationException::withMessages(['facture' => 'Seul un devis accepté peut être facturé.']);
        }
        if (in_array($type, [Facture::ACOMPTE, Facture::SITUATION], true) && (! $pourcentage || $pourcentage <= 0 || $pourcentage > 10000)) {
            throw ValidationException::withMessages(['pourcentage' => 'Indiquez un pourcentage entre 1 et 100.']);
        }

        $devis->loadMissing('lignes');
        $deja = $this->dejaFacture($devis);

        return DB::transaction(function () use ($devis, $type, $pourcentage, $userId, $deja) {
            $facture = Facture::create([
                'type' => $type,
                'client_id' => $devis->client_id,
                'devis_id' => $devis->id,
                'chantier_id' => $devis->chantier_id,
                'objet' => $devis->objet,
                'pourcentage' => $pourcentage,
                'delai_paiement_jours' => (int) reglage('documents.delai_paiement_jours'),
                'remise_type' => in_array($type, [Facture::FACTURE, Facture::SOLDE], true) ? $devis->remise_type : null,
                'remise_valeur' => in_array($type, [Facture::FACTURE, Facture::SOLDE], true) ? $devis->remise_valeur : 0,
                'created_by' => $userId,
            ]);

            $reference = $devis->numero ?? $devis->reference();
            $lignes = match ($type) {
                Facture::ACOMPTE => $this->lignesParTaux($devis, fn (int $base) => intdiv($base * $pourcentage + 5000, 10000), 'Acompte de '.Tva::formater($pourcentage)." sur le devis {$reference}"),
                Facture::SITUATION => $this->lignesSituation($devis, $pourcentage, $deja, $facture),
                Facture::SOLDE => $this->lignesSolde($devis, $deja),
                default => $this->copieLignes($devis),
            };

            $this->enregistrerLignes($facture, $lignes);
            Journal::ecrire('facture.creation', $facture->libelleType().' créée depuis le devis '.$reference, $facture);

            return $facture->fresh();
        });
    }

    /**
     * Avoir : total (annule la facture) ou partiel (un montant TTC et un motif).
     */
    public function creerAvoir(Facture $facture, ?int $montantTtc, string $motif, int $userId): Facture
    {
        if ($facture->estAvoir() || ! in_array($facture->statut, [Facture::EMISE, Facture::PAYEE], true)) {
            throw ValidationException::withMessages(['avoir' => 'Un avoir se fait sur une facture émise.']);
        }

        $restant = $facture->total_ttc - $facture->totalAvoirs();
        if ($montantTtc !== null && ($montantTtc <= 0 || $montantTtc > $restant)) {
            throw ValidationException::withMessages(['montant' => 'Le montant de l\'avoir doit être entre 0,01 € et '.Montant::formater($restant).'.']);
        }

        return DB::transaction(function () use ($facture, $montantTtc, $motif, $userId, $restant) {
            $avoir = Facture::create([
                'type' => Facture::AVOIR,
                'client_id' => $facture->client_id,
                'devis_id' => $facture->devis_id,
                'chantier_id' => $facture->chantier_id,
                'facture_origine_id' => $facture->id,
                'objet' => 'Avoir sur la facture '.$facture->numero,
                'motif' => $motif,
                'delai_paiement_jours' => 0,
                'created_by' => $userId,
            ]);

            $facture->loadMissing('lignes');
            $lignes = ($montantTtc === null || $montantTtc === $restant) && $facture->totalAvoirs() === 0
                ? $this->copieLignes($facture)
                : $this->lignesPourMontantTtc($facture, $montantTtc ?? $restant, 'Avoir sur la facture '.$facture->numero.' : '.$motif);

            $this->enregistrerLignes($avoir, $lignes);
            Journal::ecrire('avoir.creation', 'Avoir créé sur la facture '.$facture->numero, $avoir);

            return $avoir->fresh();
        });
    }

    /**
     * Émission : numéro continu attribué maintenant, échéance calculée, PDF figé (SHA-256).
     */
    public function emettre(Facture $facture): Facture
    {
        if (! $facture->estModifiable()) {
            throw ValidationException::withMessages(['facture' => 'Cette facture est déjà émise.']);
        }
        if (! $facture->lignes()->where('type', 'ligne')->exists()) {
            throw ValidationException::withMessages(['facture' => 'Ajoutez au moins une ligne avant d\'émettre la facture.']);
        }
        if ($facture->total_ttc <= 0 && ! $facture->estAvoir()) {
            throw ValidationException::withMessages(['facture' => 'Le total de la facture doit être positif.']);
        }

        DB::transaction(function () use ($facture) {
            $facture->numero = $this->numerotation->attribuer($facture->estAvoir() ? 'avoir' : 'facture');
            $facture->statut = Facture::EMISE;
            $facture->date_facture = now()->toDateString();
            $facture->date_prestation ??= now()->toDateString();
            $facture->date_echeance = now()->addDays($facture->delai_paiement_jours)->toDateString();
            $facture->emise_at = now();
            $facture->save();

            if ($facture->estAvoir()) {
                $origine = $facture->origine;
                if ($origine && $origine->total_ttc - $origine->totalAvoirs() <= 0) {
                    $origine->forceFill(['statut' => Facture::ANNULEE])->save();
                }
                $facture->forceFill(['statut' => Facture::PAYEE])->save();
            }

            $this->pdf->figer($facture->fresh(['client', 'chantier', 'lignes', 'origine', 'devis']));
        });

        Journal::ecrire('facture.emission', $facture->libelleType().' '.$facture->numero.' émise', $facture);

        return $facture;
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    public function remplacerLignes(Facture $facture, array $lignes): void
    {
        if (! $facture->estModifiable()) {
            throw ValidationException::withMessages(['facture' => 'Une facture émise ne se modifie pas. Faites un avoir.']);
        }

        DB::transaction(function () use ($facture, $lignes) {
            $facture->lignes()->delete();
            $this->enregistrerLignes($facture, $lignes);
        });
    }

    /**
     * Rappel de l'encaissement avant 7 jours (contrat hors établissement, non bloquant).
     */
    public static function rappelSeptJours(?Devis $devis): ?string
    {
        if (! $devis || ! $devis->hors_etablissement || $devis->urgence || ! $devis->accepte_at || $devis->client?->estProfessionnel()) {
            return null;
        }

        $fin = $devis->accepte_at->copy()->addDays(7);

        return $fin->isFuture()
            ? 'Contrat signé hors établissement le '.$devis->accepte_at->format('d/m/Y').' : aucun paiement ne peut être demandé ni encaissé avant le '.$fin->format('d/m/Y').'.'
            : null;
    }

    /**
     * Montants HT déjà facturés (factures émises ou brouillons) sur ce devis, par taux de TVA.
     *
     * @return array{par_taux: array<int, int>, factures: Collection<int, Facture>}
     */
    private function dejaFacture(Devis $devis): array
    {
        $factures = $devis->factures()->with('lignes')
            ->whereIn('type', [Facture::ACOMPTE, Facture::SITUATION])
            ->where('statut', '!=', Facture::ANNULEE)
            ->get();

        $parTaux = [];
        foreach ($factures as $f) {
            foreach ($f->lignes->where('type', 'ligne') as $l) {
                $parTaux[$l->taux_tva] = ($parTaux[$l->taux_tva] ?? 0) + $l->total_ht;
            }
        }

        return ['par_taux' => $parTaux, 'factures' => $factures];
    }

    /**
     * Une ligne par taux de TVA du devis (montant calculé à partir de la base HT du taux).
     *
     * @return list<array<string, mixed>>
     */
    private function lignesParTaux(Devis $devis, callable $montant, string $libelle): array
    {
        $detail = $devis->detailTotaux();
        $lignes = [];
        foreach ($detail['tva'] as $taux => $tva) {
            $lignes[] = $this->ligne($libelle.(count($detail['tva']) > 1 && ! Tva::estFranchise() ? ' (TVA '.Tva::formater($taux).')' : ''), $montant($tva['base']), $taux);
        }

        return $lignes;
    }

    /**
     * @param  array{par_taux: array<int, int>, factures: Collection<int, Facture>}  $deja
     * @return list<array<string, mixed>>
     */
    private function lignesSituation(Devis $devis, int $avancement, array $deja, Facture $facture): array
    {
        $numero = $deja['factures']->where('type', Facture::SITUATION)->count() + 1;
        $facture->forceFill(['numero_situation' => $numero])->save();

        $lignes = $this->lignesParTaux(
            $devis,
            fn (int $base) => intdiv($base * $avancement + 5000, 10000),
            "Situation n° {$numero} — avancement cumulé ".Tva::formater($avancement).' du devis '.($devis->numero ?? ''),
        );

        // On retire ce qui a déjà été facturé (acomptes et situations précédentes), taux par taux.
        foreach ($deja['par_taux'] as $taux => $montant) {
            if ($montant > 0) {
                $lignes[] = $this->ligne('À déduire : déjà facturé sur ce devis', -$montant, $taux);
            }
        }

        $total = array_sum(array_column($lignes, 'prix_unitaire_ht'));
        if ($total <= 0) {
            throw ValidationException::withMessages(['pourcentage' => 'Cet avancement a déjà été facturé. Indiquez un avancement cumulé plus élevé.']);
        }

        return $lignes;
    }

    /**
     * Solde : tout le devis, moins les acomptes et situations déjà facturés.
     *
     * @param  array{par_taux: array<int, int>, factures: Collection<int, Facture>}  $deja
     * @return list<array<string, mixed>>
     */
    private function lignesSolde(Devis $devis, array $deja): array
    {
        $lignes = $this->copieLignes($devis);
        foreach ($deja['factures'] as $f) {
            foreach ($f->lignes->where('type', 'ligne')->groupBy('taux_tva') as $taux => $groupe) {
                $lignes[] = $this->ligne('À déduire : '.mb_strtolower($f->libelleType()).' '.($f->numero ?? 'brouillon'), -$groupe->sum('total_ht'), (int) $taux);
            }
        }

        return $lignes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function copieLignes(Devis|Facture $source): array
    {
        // Les options non retenues ne sont pas facturées.
        return $source->lignes
            ->filter(fn ($l) => ! $l->option)
            ->map(fn ($l) => $l->only(['type', 'designation', 'description', 'quantite', 'unite', 'prix_unitaire_ht', 'taux_tva', 'prestation_id']))
            ->values()->all();
    }

    /**
     * Lignes d'un avoir partiel : le montant TTC est réparti selon les taux de la facture.
     *
     * @return list<array<string, mixed>>
     */
    private function lignesPourMontantTtc(Facture $facture, int $montantTtc, string $libelle): array
    {
        $detail = $facture->detailTotaux();
        $totalTtc = max(1, $facture->total_ttc);
        $lignes = [];
        $reste = $montantTtc;
        $taux = array_keys($detail['tva']);

        foreach ($taux as $i => $t) {
            $partTtc = $i === count($taux) - 1 ? $reste : intdiv(($detail['tva'][$t]['base'] + $detail['tva'][$t]['montant']) * $montantTtc + intdiv($totalTtc, 2), $totalTtc);
            $reste -= $partTtc;
            // HT tel que HT + TVA(HT) = part TTC (au centime près).
            $ht = intdiv($partTtc * 10000 + intdiv(10000 + $t, 2), 10000 + $t);
            $lignes[] = $this->ligne($libelle, $ht, $t);
        }

        return $lignes;
    }

    /**
     * @return array<string, mixed>
     */
    private function ligne(string $designation, int $montantHt, int $taux): array
    {
        return ['type' => 'ligne', 'designation' => $designation, 'quantite' => 1000, 'unite' => 'forfait', 'prix_unitaire_ht' => $montantHt, 'taux_tva' => Tva::estFranchise() ? 0 : $taux];
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    private function enregistrerLignes(Facture $facture, array $lignes): void
    {
        foreach (array_values($lignes) as $position => $ligne) {
            if (! empty($ligne['prestation_id']) && ! Prestation::whereKey($ligne['prestation_id'])->exists()) {
                $ligne['prestation_id'] = null;
            }
            $facture->lignes()->create([
                'position' => $position,
                'type' => $ligne['type'] ?? 'ligne',
                'designation' => $ligne['designation'] ?? null,
                'description' => $ligne['description'] ?? null,
                'quantite' => $ligne['quantite'] ?? 1000,
                'unite' => $ligne['unite'] ?? null,
                'prix_unitaire_ht' => $ligne['prix_unitaire_ht'] ?? 0,
                'taux_tva' => Tva::estFranchise() ? 0 : (int) ($ligne['taux_tva'] ?? reglage('tva.taux_defaut')),
                'option' => (bool) ($ligne['option'] ?? false),
                'prestation_id' => $ligne['prestation_id'] ?? null,
            ]);
        }
        $facture->unsetRelation('lignes');
        $facture->recalculer();
    }
}
