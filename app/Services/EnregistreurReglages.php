<?php

namespace App\Services;

use App\Rules\Iban;
use App\Rules\Siret;
use App\Rules\TvaIntracom;
use App\Support\FichiersReglages;
use App\Support\Reglages;
use App\Support\SectionsReglages;
use App\Support\Tva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as Validateur;

/**
 * Valide et enregistre une liste de champs de réglages (écrans Réglages et
 * menu de configuration du premier lancement).
 */
class EnregistreurReglages
{
    public function __construct(private Reglages $reglages) {}

    /**
     * @param  list<array<string, mixed>>  $champs
     * @param  array<string, mixed>  $reglesEnPlus
     * @param  array<string, string>  $messagesEnPlus
     */
    public function enregistrer(Request $request, array $champs, array $reglesEnPlus = [], array $messagesEnPlus = []): void
    {
        $section = ['champs' => $champs];
        $donnees = $this->nettoyer($request->all(), $champs);

        Validator::make(
            $donnees,
            array_merge(SectionsReglages::regles($section, $donnees), $reglesEnPlus),
            array_merge(SectionsReglages::messages($section), $messagesEnPlus),
            SectionsReglages::libelles($section),
        )->after(fn (Validateur $v) => $this->verifierTaux($v, $champs, $donnees))
            ->validate();

        foreach ($champs as $champ) {
            if (empty($champ['verrouille'])) {
                $this->enregistrerChamp($champ, $donnees, $request);
            }
        }
    }

    /**
     * Valeurs à afficher dans un formulaire (jamais les secrets).
     *
     * @param  list<array<string, mixed>>  $champs
     * @return array<string, mixed>
     */
    public function valeursAffichees(array $champs): array
    {
        $valeurs = [];

        foreach ($champs as $champ) {
            $valeur = reglage($champ['cle']);
            $valeurs[$champ['cle']] = match ($champ['type']) {
                'taux' => implode(' ; ', array_map(fn ($t) => Tva::formater((int) $t, false), (array) $valeur)),
                'lignes' => implode("\n", (array) $valeur),
                'secret' => $this->reglages->aSecret($champ['cle']),
                default => $champ['cle'] === 'documents.iban' ? Iban::formater((string) $valeur) : $valeur,
            };
        }

        return $valeurs;
    }

    /**
     * @param  list<array<string, mixed>>  $champs
     * @return array<string, mixed>
     */
    private function nettoyer(array $donnees, array $champs): array
    {
        foreach ($champs as $champ) {
            $nom = SectionsReglages::nomChamp($champ['cle']);
            if (! isset($donnees[$nom]) || ! is_string($donnees[$nom])) {
                continue;
            }

            $donnees[$nom] = match ($champ['cle']) {
                'identite.siret' => Siret::nettoyer($donnees[$nom]),
                'identite.tva_intracom' => TvaIntracom::nettoyer($donnees[$nom]),
                'identite.code_ape' => strtoupper(str_replace('.', '', trim($donnees[$nom]))),
                'documents.iban' => Iban::nettoyer($donnees[$nom]),
                'documents.bic' => strtoupper(trim($donnees[$nom])),
                default => trim($donnees[$nom]),
            };

            if ($champ['type'] === 'couleur') {
                $donnees[$nom] = strtolower($donnees[$nom]);
            }
        }

        return $donnees;
    }

    /**
     * @param  list<array<string, mixed>>  $champs
     * @param  array<string, mixed>  $donnees
     */
    private function verifierTaux(Validateur $validateur, array $champs, array $donnees): void
    {
        $cles = array_column($champs, 'cle');

        if (in_array('tva.taux', $cles, true)) {
            $taux = Tva::lireListe((string) ($donnees['tva__taux'] ?? ''));
            if ($taux === null) {
                $validateur->errors()->add('tva__taux', 'Un taux est illisible. Écrivez par exemple : 20 ; 10 ; 5,5 ; 0');

                return;
            }
        } else {
            $taux = array_map('intval', (array) reglage('tva.taux'));
        }

        if (! in_array('tva.taux_defaut', $cles, true)) {
            return;
        }

        $defaut = (int) ($donnees['tva__taux_defaut'] ?? -1);
        $regime = $donnees['tva__regime'] ?? reglage('tva.regime');
        if ($regime === 'assujetti' && ! in_array($defaut, $taux, true)) {
            $validateur->errors()->add('tva__taux_defaut', 'Le taux par défaut doit faire partie des taux utilisés.');
        }
    }

    /**
     * @param  array<string, mixed>  $champ
     * @param  array<string, mixed>  $donnees
     */
    private function enregistrerChamp(array $champ, array $donnees, Request $request): void
    {
        $cle = $champ['cle'];
        $nom = SectionsReglages::nomChamp($cle);
        $valeur = $donnees[$nom] ?? null;

        switch ($champ['type']) {
            case 'case':
                $this->reglages->set($cle, (bool) $valeur);
                break;

            case 'entier':
                $this->reglages->set($cle, (int) $valeur);
                break;

            case 'taux':
                $this->reglages->set($cle, Tva::lireListe((string) $valeur));
                break;

            case 'taux_defaut':
                $regime = $donnees['tva__regime'] ?? reglage('tva.regime');
                $this->reglages->set($cle, $regime === 'franchise' ? 0 : (int) $valeur);
                break;

            case 'lignes':
                $lignes = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $valeur)))));
                $this->reglages->set($cle, $lignes);
                break;

            case 'secret':
                if ($request->boolean($nom.'__effacer')) {
                    $this->reglages->setSecret($cle, null);
                } elseif (is_string($valeur) && $valeur !== '') {
                    $this->reglages->setSecret($cle, str_replace(' ', '', $valeur));
                }
                break;

            case 'fichier':
                if ($request->boolean($nom.'__effacer')) {
                    FichiersReglages::supprimer($cle);
                } elseif ($request->hasFile($nom)) {
                    FichiersReglages::enregistrer($cle, $request->file($nom));
                }
                break;

            default:
                $this->reglages->set($cle, $valeur === null ? '' : (string) $valeur);
        }
    }
}
