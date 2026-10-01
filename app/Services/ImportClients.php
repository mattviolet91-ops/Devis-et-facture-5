<?php

namespace App\Services;

use App\Models\Client;
use App\Support\Telephone;
use App\Support\Texte;
use Illuminate\Support\Facades\Validator;

/**
 * Import de clients depuis un fichier CSV (export Excel, Google Contacts, ancien logiciel…).
 * 1. analyser() lit le fichier et prépare un aperçu ligne par ligne ;
 * 2. importer() crée les clients retenus.
 */
class ImportClients
{
    public const LIGNES_MAX = 2000;

    /**
     * Libellés de colonnes reconnus (sans accents ni majuscules).
     *
     * @var array<string, list<string>>
     */
    public const COLONNES = [
        'civilite' => ['civilite', 'titre', 'title'],
        'nom' => ['nom', 'nom de famille', 'name', 'last name', 'family name', 'nom complet', 'client', 'nom du client'],
        'prenom' => ['prenom', 'first name', 'given name'],
        'raison_sociale' => ['societe', 'entreprise', 'raison sociale', 'company', 'organisation', 'organization'],
        'telephone' => ['telephone', 'tel', 'tel.', 'portable', 'mobile', 'phone', 'gsm', 'telephone 1', 'phone 1 - value'],
        'telephone2' => ['telephone 2', 'tel 2', 'fixe', 'autre telephone', 'phone 2 - value'],
        'email' => ['email', 'e-mail', 'mail', 'courriel', 'adresse email', 'adresse e-mail', 'e-mail 1 - value'],
        'adresse' => ['adresse', 'rue', 'address', 'adresse postale'],
        'code_postal' => ['code postal', 'cp', 'postal code', 'zip', 'code'],
        'ville' => ['ville', 'commune', 'city', 'localite'],
        'provenance' => ['provenance', 'source', 'origine'],
    ];

    /**
     * @return array{colonnes: array<int, string>, lignes: list<array{numero: int, donnees: array<string, string|null>, statut: string, message: string}>}
     */
    public function analyser(string $chemin): array
    {
        $lignesBrutes = $this->lireCsv($chemin);
        $entete = array_shift($lignesBrutes) ?? [];
        $colonnes = $this->reconnaitreColonnes($entete);

        $resultat = [];
        $vus = ['telephone' => [], 'email' => []];

        foreach (array_slice($lignesBrutes, 0, self::LIGNES_MAX) as $i => $cellules) {
            if (count(array_filter($cellules, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $donnees = [];
            foreach ($colonnes as $index => $champ) {
                $valeur = trim((string) ($cellules[$index] ?? ''));
                $donnees[$champ] = $valeur === '' ? null : $valeur;
            }
            $donnees['type'] = ! empty($donnees['raison_sociale']) ? Client::PROFESSIONNEL : Client::PARTICULIER;

            [$statut, $message] = $this->verifier($donnees, $vus);

            if ($statut !== 'erreur') {
                if ($tel = Telephone::normaliser($donnees['telephone'] ?? null)) {
                    $vus['telephone'][] = $tel;
                }
                if (! empty($donnees['email'])) {
                    $vus['email'][] = mb_strtolower($donnees['email']);
                }
            }

            $resultat[] = ['numero' => $i + 2, 'donnees' => $donnees, 'statut' => $statut, 'message' => $message];
        }

        return ['colonnes' => $colonnes, 'lignes' => $resultat];
    }

    /**
     * @param  list<array{donnees: array<string, string|null>, statut: string}>  $lignes
     * @return array{crees: int, ignores: int}
     */
    public function importer(array $lignes, bool $avecDoublons, int $userId): array
    {
        $crees = 0;
        $ignores = 0;

        foreach ($lignes as $ligne) {
            $retenue = $ligne['statut'] === 'ok' || ($avecDoublons && $ligne['statut'] === 'doublon');
            if (! $retenue) {
                $ignores++;

                continue;
            }

            Client::create($ligne['donnees'] + ['created_by' => $userId]);
            $crees++;
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }

    /**
     * @param  list<string|null>  $entete
     * @return array<int, string>
     */
    public function reconnaitreColonnes(array $entete): array
    {
        $colonnes = [];

        foreach ($entete as $index => $libelle) {
            $libelle = Texte::pourRecherche((string) $libelle);
            foreach (self::COLONNES as $champ => $synonymes) {
                if (in_array($libelle, $synonymes, true) && ! in_array($champ, $colonnes, true)) {
                    $colonnes[$index] = $champ;
                    break;
                }
            }
        }

        return $colonnes;
    }

    /**
     * @param  array<string, string|null>  $donnees
     * @param  array{telephone: list<string>, email: list<string>}  $vus
     * @return array{string, string}
     */
    private function verifier(array &$donnees, array $vus): array
    {
        if (empty($donnees['nom']) && empty($donnees['raison_sociale'])) {
            return ['erreur', 'Nom manquant'];
        }
        if (empty($donnees['telephone']) && empty($donnees['email'])) {
            return ['erreur', 'Ni téléphone ni email'];
        }
        if (! empty($donnees['email']) && Validator::make($donnees, ['email' => 'email'])->fails()) {
            return ['erreur', 'Email non valable'];
        }
        if (! empty($donnees['code_postal'])) {
            $cp = preg_replace('/\D/', '', $donnees['code_postal']);
            $donnees['code_postal'] = strlen($cp) === 4 ? '0'.$cp : $cp; // Excel retire le 0 du début.
            if (strlen($donnees['code_postal']) !== 5) {
                $donnees['code_postal'] = null;
            }
        }
        if (! empty($donnees['civilite'])) {
            $civilite = Texte::pourRecherche($donnees['civilite']);
            $donnees['civilite'] = match ($civilite) {
                'm', 'm.', 'mr', 'monsieur' => 'M.',
                'mme', 'madame', 'mlle', 'mademoiselle' => 'Mme',
                default => null,
            };
        }

        $tel = Telephone::normaliser($donnees['telephone'] ?? null);
        $email = ! empty($donnees['email']) ? mb_strtolower($donnees['email']) : null;

        if (($tel && in_array($tel, $vus['telephone'], true)) || ($email && in_array($email, $vus['email'], true))) {
            return ['doublon', 'Déjà présent plus haut dans le fichier'];
        }
        if ($existant = Client::doublons($tel, $email)->first()) {
            return ['doublon', 'Déjà dans l\'application : '.$existant->nomComplet()];
        }

        return ['ok', ''];
    }

    /**
     * @return list<list<string|null>>
     */
    private function lireCsv(string $chemin): array
    {
        $contenu = (string) file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? '';

        // Fichiers enregistrés par Excel en « CSV (séparateur : point-virgule) » : Windows-1252.
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $premiereLigne = strtok($contenu, "\r\n") ?: '';
        $separateur = collect([';', ',', "\t"])->sortByDesc(fn ($s) => substr_count($premiereLigne, $s))->first();

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $lignes = [];
        while (($ligne = fgetcsv($flux, 0, $separateur, '"', '')) !== false) {
            $lignes[] = $ligne;
        }
        fclose($flux);

        return $lignes;
    }
}
