<?php

namespace App\Services;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Prestation;
use App\Support\Journal;
use App\Support\Montant;
use App\Support\Quantite;
use App\Support\Tva;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'un devis : création, lignes, envoi (numéro), acceptation,
 * refus, expiration, nouvelle version, duplication.
 */
class GestionDevis
{
    public function __construct(private Numerotation $numerotation) {}

    public function creer(Client $client, ?Chantier $chantier, ?string $objet, int $userId): Devis
    {
        $devis = Devis::create([
            'client_id' => $client->id,
            'chantier_id' => $chantier?->id,
            'objet' => $objet,
            'validite_jours' => (int) reglage('documents.validite_devis_jours'),
            'acompte_pourcentage' => (int) reglage('documents.acompte_pourcentage'),
            'dechets_estimation' => null,
            'created_by' => $userId,
        ]);

        Journal::ecrire('devis.creation', 'Devis créé pour '.$client->nomComplet(), $devis);

        return $devis;
    }

    /**
     * Remplace toutes les lignes d'un brouillon.
     *
     * @param  list<array<string, mixed>>  $lignes  Lignes déjà validées (quantité en millièmes, prix en centimes).
     */
    public function remplacerLignes(Devis $devis, array $lignes): void
    {
        $this->verifierModifiable($devis);

        DB::transaction(function () use ($devis, $lignes) {
            $devis->lignes()->delete();
            foreach (array_values($lignes) as $position => $ligne) {
                if (! empty($ligne['prestation_id']) && ! Prestation::whereKey($ligne['prestation_id'])->exists()) {
                    $ligne['prestation_id'] = null;
                }
                $devis->lignes()->create([
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

                if (! empty($ligne['prestation_id'])) {
                    Prestation::whereKey($ligne['prestation_id'])->increment('utilisations');
                }
            }
            $devis->unsetRelation('lignes');
            $devis->recalculer();
        });
    }

    /**
     * Envoi : le numéro est attribué maintenant, le devis ne se modifie plus.
     */
    public function marquerEnvoye(Devis $devis): Devis
    {
        $this->verifierModifiable($devis);

        if (! $devis->lignes()->where('type', 'ligne')->exists()) {
            throw ValidationException::withMessages(['devis' => 'Ajoutez au moins une ligne avant d\'envoyer le devis.']);
        }
        if ($devis->lignes()->where('type', 'ligne')->where('option', false)->where('prix_unitaire_ht', 0)->exists()) {
            throw ValidationException::withMessages(['devis' => 'Une ligne n\'a pas de prix. Complétez-la avant d\'envoyer.']);
        }

        DB::transaction(function () use ($devis) {
            $origine = $devis->origine;

            $devis->numero = $origine?->numero
                ? preg_replace('/-V\d+$/', '', $origine->numero).'-V'.$devis->version
                : $this->numerotation->attribuer('devis');
            $devis->statut = Devis::ENVOYE;
            $devis->date_devis ??= now()->toDateString();
            $devis->envoye_at = now();
            $devis->save();

            // Le PDF envoyé est figé : son empreinte SHA-256 prouve qu'il n'a pas changé.
            app(PdfDevis::class)->figer($devis->fresh(['client', 'chantier', 'lignes']));

            // L'ancienne version est remplacée.
            if ($origine) {
                Devis::where(fn ($q) => $q->whereKey($origine->id)->orWhere('devis_origine_id', $origine->id))
                    ->whereKeyNot($devis->id)
                    ->whereIn('statut', [Devis::ENVOYE, Devis::REFUSE, Devis::EXPIRE])
                    ->update(['statut' => Devis::REMPLACE, 'remplace_par_id' => $devis->id]);
            }
        });

        Journal::ecrire('devis.envoi', 'Devis '.$devis->numero.' envoyé', $devis);

        return $devis;
    }

    public function accepter(Devis $devis): void
    {
        $this->verifierStatut($devis, [Devis::ENVOYE, Devis::EXPIRE]);
        $devis->forceFill(['statut' => Devis::ACCEPTE, 'accepte_at' => now()])->save();
        Journal::ecrire('devis.accepte', 'Devis '.$devis->reference().' accepté', $devis);
    }

    public function refuser(Devis $devis, ?string $motif): void
    {
        $this->verifierStatut($devis, [Devis::ENVOYE, Devis::EXPIRE]);
        $devis->forceFill(['statut' => Devis::REFUSE, 'refuse_at' => now(), 'motif_refus' => $motif])->save();
        Journal::ecrire('devis.refuse', 'Devis '.$devis->reference().' refusé', $devis);
    }

    /**
     * Nouvelle version d'un devis envoyé : copie modifiable, l'ancien sera « remplacé » à l'envoi.
     */
    public function nouvelleVersion(Devis $devis, int $userId): Devis
    {
        $this->verifierStatut($devis, [Devis::ENVOYE, Devis::REFUSE, Devis::EXPIRE]);
        $origine = $devis->origine ?? $devis;
        $version = (int) Devis::where('id', $origine->id)->orWhere('devis_origine_id', $origine->id)->max('version') + 1;

        return $this->copier($devis, $devis->client_id, $userId, ['version' => $version, 'devis_origine_id' => $origine->id]);
    }

    public function dupliquer(Devis $devis, int $clientId, int $userId): Devis
    {
        return $this->copier($devis, $clientId, $userId, ['version' => 1, 'devis_origine_id' => null]);
    }

    /**
     * Devis envoyés dont la validité est dépassée.
     */
    public function expirerLesDevisEchus(): int
    {
        $total = 0;
        Devis::where('statut', Devis::ENVOYE)->get()->each(function (Devis $devis) use (&$total) {
            if ($devis->dateValidite()?->endOfDay()->isPast()) {
                $devis->forceFill(['statut' => Devis::EXPIRE])->save();
                $total++;
            }
        });

        return $total;
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function copier(Devis $source, int $clientId, int $userId, array $attributs): Devis
    {
        return DB::transaction(function () use ($source, $clientId, $userId, $attributs) {
            $copie = Devis::create(array_merge($source->only([
                'chantier_id', 'objet', 'validite_jours', 'acompte_pourcentage', 'date_debut_travaux', 'duree_travaux',
                'dechets_estimation', 'remise_type', 'remise_valeur', 'conditions', 'hors_etablissement', 'urgence',
            ]), $attributs, ['client_id' => $clientId, 'created_by' => $userId]));

            if ($clientId !== $source->client_id) {
                $copie->chantier_id = null;
                $copie->save();
            }

            foreach ($source->lignes as $ligne) {
                $copie->lignes()->create($ligne->only([
                    'position', 'type', 'designation', 'description', 'quantite', 'unite', 'prix_unitaire_ht', 'taux_tva', 'option', 'prestation_id',
                ]));
            }
            $copie->recalculer();

            Journal::ecrire('devis.copie', 'Copie du devis '.$source->reference(), $copie);

            return $copie;
        });
    }

    private function verifierModifiable(Devis $devis): void
    {
        if (! $devis->estModifiable()) {
            throw ValidationException::withMessages(['devis' => 'Ce devis a été envoyé : il ne se modifie plus. Faites une nouvelle version.']);
        }
    }

    /**
     * @param  list<string>  $statuts
     */
    private function verifierStatut(Devis $devis, array $statuts): void
    {
        if (! in_array($devis->statut, $statuts, true)) {
            throw ValidationException::withMessages(['devis' => 'Cette action n\'est pas possible pour un devis « '.$devis->libelleStatut().' ».']);
        }
    }

    /**
     * Convertit les lignes saisies (texte) en lignes à enregistrer.
     *
     * @param  array<int, array<string, mixed>>  $saisies
     * @return array{lignes: list<array<string, mixed>>, erreurs: array<string, string>}
     */
    public static function lireLignes(array $saisies, bool $negatifsAutorises = false): array
    {
        $lignes = [];
        $erreurs = [];

        foreach (array_values($saisies) as $i => $saisie) {
            $type = in_array($saisie['type'] ?? 'ligne', ['ligne', 'section', 'texte'], true) ? $saisie['type'] ?? 'ligne' : 'ligne';
            $designation = trim((string) ($saisie['designation'] ?? ''));
            $ligne = ['type' => $type, 'designation' => mb_substr($designation, 0, 255), 'description' => trim((string) ($saisie['description'] ?? '')) ?: null];

            if ($type === 'ligne') {
                $quantite = Quantite::lire((string) ($saisie['quantite'] ?? ''));
                // Prix vide accepté sur un brouillon (à compléter) : l'envoi sera bloqué tant qu'il manque.
                $prixSaisi = trim((string) ($saisie['prix'] ?? ''));
                $prix = $prixSaisi === '' ? 0 : Montant::lire($prixSaisi);
                // Sur une facture, une ligne de déduction (acompte déjà versé) peut être négative.
                if ($prix !== null && $prix < 0 && ! $negatifsAutorises) {
                    $prix = null;
                }
                if ($designation === '') {
                    $erreurs["lignes.$i.designation"] = 'Ligne '.($i + 1).' : indiquez la désignation.';
                }
                if ($quantite === null) {
                    $erreurs["lignes.$i.quantite"] = 'Ligne '.($i + 1).' : quantité illisible (exemple : 12,5).';
                }
                if ($prix === null) {
                    $erreurs["lignes.$i.prix"] = 'Ligne '.($i + 1).' : prix illisible (exemple : 45,00).';
                }
                $taux = (int) ($saisie['taux_tva'] ?? reglage('tva.taux_defaut'));
                if (! Tva::estFranchise() && ! in_array($taux, array_map('intval', (array) reglage('tva.taux')), true)) {
                    $erreurs["lignes.$i.taux_tva"] = 'Ligne '.($i + 1).' : taux de TVA inconnu.';
                }
                $ligne += [
                    'quantite' => $quantite ?? 0,
                    'unite' => mb_substr((string) ($saisie['unite'] ?? 'u'), 0, 20),
                    'prix_unitaire_ht' => $prix ?? 0,
                    'taux_tva' => $taux,
                    'option' => ! empty($saisie['option']),
                    'prestation_id' => ! empty($saisie['prestation_id']) ? (int) $saisie['prestation_id'] : null,
                ];
            } elseif ($designation === '' && $type === 'section') {
                $erreurs["lignes.$i.designation"] = 'Ligne '.($i + 1).' : donnez un titre à la section.';
            }

            $lignes[] = $ligne;
        }

        return ['lignes' => $lignes, 'erreurs' => $erreurs];
    }
}
