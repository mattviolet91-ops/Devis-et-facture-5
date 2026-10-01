<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Prestation;
use App\Support\Montant;
use App\Support\Quantite;
use App\Support\Texte;
use App\Support\Tva;

/**
 * Devis express : une phrase écrite ou dictée devient un devis brouillon.
 *
 *   « Mme Martin, démoussage 120 m² à 12 €, 3 faîtières à 150 €, évacuation forfait 150 € »
 *
 * Règle d'or : rien n'est deviné. Client introuvable ou ambigu, prix manquant,
 * quantité absente ou illisible : la création est bloquée et l'on explique pourquoi.
 */
class DevisExpress
{
    /** Unités reconnues (texte normalisé → unité de l'application). */
    private const UNITES = [
        'm2' => 'm²', 'm²' => 'm²', 'metre carre' => 'm²', 'metres carres' => 'm²', 'metre carres' => 'm²', 'mc' => 'm²',
        'ml' => 'ml', 'metre lineaire' => 'ml', 'metres lineaires' => 'ml', 'm lineaire' => 'ml', 'm lineaires' => 'ml',
        'm3' => 'm³', 'm³' => 'm³', 'metre cube' => 'm³', 'metres cubes' => 'm³',
        'h' => 'h', 'heure' => 'h', 'heures' => 'h',
        'jour' => 'jour', 'jours' => 'jour', 'j' => 'jour',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
        'tonne' => 't', 'tonnes' => 't', 't' => 't',
        'u' => 'u', 'unite' => 'u', 'unites' => 'u', 'piece' => 'u', 'pieces' => 'u',
        'm' => 'ml', 'metre' => 'ml', 'metres' => 'ml',
    ];

    /** Nombre français : « 1 200 », « 1200 », « 12,5 », « 1 500,50 ». */
    private const NOMBRE = '\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+(?:[.,]\d{1,3})?|\d+(?:[.,]\d{1,3})?';

    /**
     * @return array{client: ?Client, lignes: list<array<string, mixed>>, erreurs: list<string>, alertes: list<string>, total_ht: int}
     */
    public function analyser(string $phrase): array
    {
        $erreurs = [];
        $alertes = [];

        $phrase = $this->nettoyer($phrase);
        if ($phrase === '') {
            return ['client' => null, 'lignes' => [], 'erreurs' => ['Écrivez ou dictez votre devis.'], 'alertes' => [], 'total_ht' => 0];
        }

        [$client, $reste, $erreurClient] = $this->trouverClient($phrase);
        if ($erreurClient) {
            $erreurs[] = $erreurClient;
        }

        if (preg_match('/\bttc\b/iu', $reste)) {
            $alertes[] = 'Vous avez écrit « TTC » : les prix d\'un devis sont saisis HT. Vérifiez les montants.';
        }

        $lignes = [];
        foreach ($this->decouper($reste) as $segment) {
            $ligne = $this->lireSegment($segment);
            foreach ($ligne['erreurs'] as $e) {
                $erreurs[] = $e;
            }
            foreach ($ligne['alertes'] as $a) {
                $alertes[] = $a;
            }
            unset($ligne['erreurs'], $ligne['alertes']);
            $lignes[] = $ligne;
        }

        if ($lignes === [] && ! $erreurClient) {
            $erreurs[] = 'Aucune prestation trouvée après le nom du client.';
        }

        $total = array_sum(array_map(fn ($l) => Quantite::total($l['quantite'] ?? 0, $l['prix_unitaire_ht'] ?? 0), $lignes));

        return ['client' => $client, 'lignes' => $lignes, 'erreurs' => $erreurs, 'alertes' => array_values(array_unique($alertes)), 'total_ht' => $total];
    }

    private function nettoyer(string $phrase): string
    {
        $phrase = str_replace(["\u{00A0}", "\u{202F}", '’', '`'], [' ', ' ', "'", "'"], $phrase);
        $phrase = preg_replace('/\beuros?\b/iu', '€', $phrase) ?? $phrase;
        $phrase = preg_replace('/^\s*(?:(?:un|nouveau)\s+)?devis\s+(?:pour|de|à)\s+/iu', '', $phrase) ?? $phrase;

        return trim(preg_replace('/[ \t]+/', ' ', $phrase) ?? '');
    }

    /**
     * Le client est en tête de phrase : « Mme Martin, … » ou collé à la première prestation
     * (« Mme Martin démoussage 120 m² … »). On prend le plus long début qui correspond à un client.
     *
     * @return array{?Client, string, ?string}
     */
    private function trouverClient(string $phrase): array
    {
        $civilites = '(?:m\.?|mr\.?|mme|madame|monsieur|mlle|mademoiselle|m\.? et mme|monsieur et madame)';
        $sansCivilite = preg_replace('/^'.$civilites.'\s+/iu', '', $phrase) ?? $phrase;

        // Mots du début (jusqu'à 6), en s'arrêtant à la première ponctuation forte.
        preg_match('/^[^,;:\n.]*/u', $sansCivilite, $m);
        $debut = $m[0];
        $mots = preg_split('/\s+/', trim($debut)) ?: [];

        $index = $this->indexClients();
        $trouve = null;
        $longueur = 0;
        $ambigus = [];

        for ($n = min(6, count($mots)); $n >= 1; $n--) {
            $cle = Texte::pourRecherche(implode(' ', array_slice($mots, 0, $n)));
            if (isset($index[$cle])) {
                $candidats = array_values(array_unique($index[$cle]));
                if (count($candidats) > 1) {
                    $ambigus = $candidats;
                } else {
                    $trouve = $candidats[0];
                }
                $longueur = $n;
                break;
            }
        }

        if ($longueur === 0) {
            $nom = trim(implode(' ', array_slice($mots, 0, 2)));

            return [null, $this->apresClient($sansCivilite, 0), 'Client introuvable : « '.($nom ?: $phrase).' ». Créez d\'abord le client, ou écrivez son nom comme dans sa fiche.'];
        }

        // Retire les mots du client (avec leurs espaces d'origine) puis la ponctuation qui suit.
        $reste = $this->apresClient($sansCivilite, $longueur);

        if ($ambigus) {
            $noms = Client::whereIn('id', $ambigus)->get()->map(fn (Client $c) => $c->nomComplet().($c->ville ? ' ('.$c->ville.')' : ''))->implode(', ');

            return [null, $reste, 'Plusieurs clients correspondent : '.$noms.'. Ajoutez le prénom ou la ville pour préciser.'];
        }

        return [Client::find($trouve), $reste, null];
    }

    private function apresClient(string $texte, int $nombreDeMots): string
    {
        $reste = $nombreDeMots > 0
            ? preg_replace('/^(?:\S+\s*){'.$nombreDeMots.'}/u', '', $texte) ?? ''
            : preg_replace('/^[^,;:\n]*[,;:\n]/u', '', $texte) ?? '';

        return ltrim($reste, " ,;:.\n-");
    }

    /**
     * Index nom → clients : « martin », « sophie martin », « martin sophie », raison sociale, avec la ville.
     *
     * @return array<string, list<int>>
     */
    private function indexClients(): array
    {
        $index = [];
        Client::query()->get(['id', 'nom', 'prenom', 'raison_sociale', 'ville'])->each(function (Client $c) use (&$index) {
            $cles = [];
            if ($c->nom) {
                $cles[] = $c->nom;
                if ($c->prenom) {
                    $cles[] = $c->prenom.' '.$c->nom;
                    $cles[] = $c->nom.' '.$c->prenom;
                }
                if ($c->ville) {
                    $cles[] = $c->nom.' '.$c->ville;
                    $cles[] = $c->nom.' à '.$c->ville;
                    $cles[] = $c->nom.' a '.$c->ville;
                }
            }
            if ($c->raison_sociale) {
                $cles[] = $c->raison_sociale;
                // « SCI Les Tilleuls (Paris) » se dit aussi « SCI Les Tilleuls ».
                $cles[] = trim(preg_replace('/\s*\([^)]*\)/u', '', $c->raison_sociale) ?? '');
            }
            foreach ($cles as $cle) {
                $index[Texte::pourRecherche($cle)][] = $c->id;
            }
        });

        return $index;
    }

    /**
     * Prestations séparées par une virgule, un point, « et », un tiret, un point-virgule ou un retour à la ligne.
     * Une virgule ou un point entre deux chiffres (12,50 / 1.5) n'est pas un séparateur.
     *
     * @return list<string>
     */
    private function decouper(string $texte): array
    {
        // Virgule ou point entre deux chiffres : décimale, pas un séparateur.
        $protege = preg_replace('/(?<=\d)[,.](?=\d)/u', '§', $texte) ?? $texte;
        $morceaux = preg_split('/[,.;\n]+|\s+et\s+|\s+-\s+|^\s*-\s*/iu', $protege) ?: [];

        return array_values(array_filter(
            array_map(fn ($m) => trim(str_replace('§', ',', $m)), $morceaux),
            fn ($m) => $m !== '' && preg_match('/\p{L}|\d/u', $m),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function lireSegment(string $segment): array
    {
        $original = $segment;
        $erreurs = [];
        $alertes = [];
        $quantite = null;
        $unite = null;
        $prix = null;
        $unitePrix = null;
        $nombre = self::NOMBRE;
        $motsUnite = '(?:m²|m2|m³|m3|mètres?\s+carrés?|metres?\s+carres?|mètres?\s+linéaires?|metres?\s+lineaires?|m\s+linéaires?|mètres?\s+cubes?|metres?\s+cubes?|ml|mc|heures?|h|jours?|kg|kilos?|tonnes?|t|unités?|unites?|pièces?|pieces?|mètres?|metres?|m)';

        // 1. « x3 », « x 3 », « 3x » : quantité explicite (« x3 150 € » = 3 × 150 €).
        if (preg_match('/(?:^|\s)[x×]\s?(\d+(?:[.,]\d+)?)(?=\s|$)|(?:^|\s)(\d+(?:[.,]\d+)?)\s?[x×](?=\s|$)/iu', $segment, $m)) {
            $quantite = Quantite::lire($m[1] !== '' ? $m[1] : $m[2]);
            $segment = str_replace($m[0], ' ', $segment);
        }

        // 2. Prix : « 12 € », « 1 500 € », « 8 € le mètre linéaire », « 45 € de l'heure », « 9 €/m² », « 12 € HT ».
        if (preg_match('/(?:à|a|au prix de|pour|de)?\s*('.$nombre.')\s*€(?:\s*(?:ht|ttc))?(?:\s*(?:\/|le|la|par|du|de\s+l\'|de\s+la)\s*('.$motsUnite.'))?/iu', $segment, $m)) {
            $prix = Montant::lire(str_replace('.', ',', preg_replace('/[ \x{00A0}\x{202F}]/u', '', $m[1]) ?? ''));
            $unitePrix = isset($m[2]) && $m[2] !== '' ? $this->unite($m[2]) : null;
            $segment = str_replace($m[0], ' ', $segment);
        }
        if (preg_match_all('/€/u', $segment) > 0) {
            $erreurs[] = 'Prix illisible dans « '.trim($original).' ».';
        }

        // 3. « forfait ».
        if (preg_match('/\b(?:au\s+)?forfait(?:aire)?\b/iu', $segment, $m)) {
            if ($quantite === null) {
                $quantite = 1000;
            }
            $unite = 'forfait';
            $segment = str_replace($m[0], ' ', $segment);
        }

        // 4. Quantité avec unité : « 120 m² », « 1 200 m² », « 120 mètres carrés », « 3 h ».
        if ($unite === null && preg_match('/('.$nombre.')\s*('.$motsUnite.')(?=\s|$|\W)/iu', $segment, $m)) {
            $q = Quantite::lire(preg_replace('/[ \x{00A0}\x{202F}]/u', '', $m[1]) ?? '');
            if ($quantite === null) {
                $quantite = $q;
            }
            $unite = $this->unite($m[2]);
            $segment = str_replace($m[0], ' ', $segment);
        }

        // 5. Nombre devant un nom : « 3 faîtières ».
        if ($quantite === null && preg_match('/(?:^|\s)('.$nombre.')\s+(?=\p{L})/u', $segment, $m)) {
            $quantite = Quantite::lire(preg_replace('/[ \x{00A0}\x{202F}]/u', '', $m[1]) ?? '');
            $segment = preg_replace('/'.preg_quote($m[0], '/').'/u', ' ', $segment, 1) ?? $segment;
        }

        $unite ??= $unitePrix ?? 'u';
        if ($unitePrix && $unite !== $unitePrix && $unite !== 'u') {
            $alertes[] = 'Unités différentes dans « '.trim($original).' » ('.$unite.' et prix par '.$unitePrix.').';
        }

        // Désignation : ce qui reste, sans les petits mots de liaison.
        $designation = preg_replace('/\b(?:à|a|au|pour|de|du|des|le|la|les|prix|soit|total)\b\s*$/iu', '', trim($segment)) ?? '';
        $designation = trim(preg_replace('/^\s*(?:de|du|des|d\'|le|la|les|un|une)\s+/iu', '', preg_replace('/\s+/', ' ', $designation) ?? '') ?? '', " \t-:");
        $designation = mb_strtoupper(mb_substr($designation, 0, 1)).mb_substr($designation, 1);

        $quantiteIllisible = (bool) preg_match('/\d/', $designation);
        if ($quantiteIllisible) {
            $erreurs[] = 'Quantité illisible dans « '.trim($original).' » : écrivez par exemple « 120 m² » ou « x3 ».';
        }
        if ($designation === '') {
            $erreurs[] = 'Prestation sans nom dans « '.trim($original).' ».';
        }

        // Prestation du catalogue reprise seulement si son nom est vraiment écrit.
        [$prestation, $proche] = $this->chercherPrestation($designation);
        if ($prestation) {
            $designation = $prestation->nom;
            if ($unite === 'u' && $prestation->unite) {
                $unite = $prestation->unite;
            }
            if ($prix === null && $prestation->prix_ht !== null) {
                $prix = $prestation->prix_ht;
                $alertes[] = 'Prix du catalogue repris pour « '.$prestation->nom.' » : '.Montant::formater($prix).'.';
            }
        } elseif ($proche) {
            $alertes[] = '« '.$designation.' » : prestation proche dans le catalogue, « '.$proche->nom.' ». La ligne reste telle que vous l\'avez écrite.';
        }

        if ($prix === null) {
            $erreurs[] = 'Prix manquant pour « '.($designation ?: trim($original)).' ».';
        }
        if (! $quantiteIllisible && ($quantite === null || $quantite <= 0)) {
            $erreurs[] = 'Quantité manquante pour « '.($designation ?: trim($original)).' » : écrivez une quantité (« 3 », « 120 m² », « x2 ») ou « forfait ».';
        }

        return [
            'type' => 'ligne',
            'designation' => $designation,
            'quantite' => $quantite ?? 0,
            'unite' => $unite,
            'prix_unitaire_ht' => $prix ?? 0,
            'taux_tva' => $prestation?->tauxEffectif() ?? (Tva::estFranchise() ? 0 : (int) reglage('tva.taux_defaut')),
            'option' => false,
            'prestation_id' => $prestation?->id,
            'erreurs' => $erreurs,
            'alertes' => $alertes,
        ];
    }

    private function unite(string $texte): string
    {
        $cle = Texte::pourRecherche(str_replace(['²', '³'], ['2', '3'], $texte));

        return self::UNITES[$cle] ?? self::UNITES[rtrim($cle, 's')] ?? 'u';
    }

    /**
     * @return array{?Prestation, ?Prestation} [prestation dont le nom est écrit, prestation proche]
     */
    private function chercherPrestation(string $designation): array
    {
        $texte = ' '.$this->singulier(Texte::pourRecherche($designation)).' ';
        if (trim($texte) === '') {
            return [null, null];
        }

        $meilleure = null;
        $proche = null;
        $scoreProche = 0;

        foreach (Prestation::query()->get(['id', 'nom', 'unite', 'prix_ht', 'taux_tva']) as $prestation) {
            $nom = $this->singulier(Texte::pourRecherche($prestation->nom));
            if ($nom === '') {
                continue;
            }
            if (str_contains($texte, ' '.$nom.' ')) {
                if (! $meilleure || mb_strlen($nom) > mb_strlen($this->singulier(Texte::pourRecherche($meilleure->nom)))) {
                    $meilleure = $prestation;
                }

                continue;
            }

            // Prestation proche : un mot important en commun.
            $communs = array_intersect(
                array_filter(explode(' ', $nom), fn ($m) => mb_strlen($m) >= 4),
                array_filter(explode(' ', trim($texte)), fn ($m) => mb_strlen($m) >= 4),
            );
            if (count($communs) > $scoreProche) {
                $scoreProche = count($communs);
                $proche = $prestation;
            }
        }

        return [$meilleure, $meilleure ? null : $proche];
    }

    private function singulier(string $texte): string
    {
        return implode(' ', array_map(fn ($m) => mb_strlen($m) > 3 ? preg_replace('/(?<=[a-z])[sx]$/', '', $m) : $m, explode(' ', $texte)));
    }
}
