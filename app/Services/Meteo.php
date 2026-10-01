<?php

namespace App\Services;

use App\Models\LieuGeocode;
use App\Models\MeteoPrevision;
use App\Models\RendezVous;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Météo des chantiers : adresse → position (Géoplateforme IGN), puis prévisions (MET Norway).
 * Les appels à Internet se font UNIQUEMENT dans la tâche horaire « app:meteo » ;
 * l'affichage lit le cache en base, sans aucun appel réseau.
 */
class Meteo
{
    public const URL_GEOCODAGE = 'https://data.geopf.fr/geocodage/search';

    public const URL_PREVISIONS = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

    /** Jours de prévision gardés (MET Norway donne environ 9 jours). */
    public const JOURS = 9;

    public static function cleRecherche(string $adresse): string
    {
        return Str::limit(Str::lower(Str::squish($adresse)), 180, '');
    }

    public static function point(float $latitude, float $longitude): string
    {
        // Deux décimales (environ 1 km) : assez précis et respecte les conditions de MET Norway.
        return number_format($latitude, 2, '.', '').','.number_format($longitude, 2, '.', '');
    }

    /**
     * Position d'une adresse, depuis le cache ou (dans la tâche horaire seulement) l'IGN.
     */
    public function situer(string $adresse, bool $reseau = false): ?LieuGeocode
    {
        $cle = self::cleRecherche($adresse);
        $lieu = LieuGeocode::where('recherche', $cle)->first();
        if ($lieu || ! $reseau) {
            return $lieu?->latitude !== null ? $lieu : null;
        }

        $reponse = Http::timeout(10)->acceptJson()->get(self::URL_GEOCODAGE, ['q' => $adresse, 'limit' => 1]);
        if (! $reponse->successful()) {
            return null;
        }

        $resultat = $reponse->json('features.0');
        $coordonnees = $resultat['geometry']['coordinates'] ?? null;

        // On garde aussi les adresses introuvables pour ne pas redemander à chaque heure.
        $lieu = LieuGeocode::create([
            'recherche' => $cle,
            'longitude' => is_array($coordonnees) ? (float) $coordonnees[0] : null,
            'latitude' => is_array($coordonnees) ? (float) $coordonnees[1] : null,
            'libelle' => $resultat['properties']['label'] ?? null,
        ]);

        return $lieu->latitude !== null ? $lieu : null;
    }

    /**
     * Télécharge et range les prévisions journalières d'un point.
     */
    public function telecharger(string $point): int
    {
        [$lat, $lon] = explode(',', $point);
        $reponse = Http::timeout(15)
            ->withUserAgent($this->userAgent())
            ->acceptJson()
            ->get(self::URL_PREVISIONS, ['lat' => $lat, 'lon' => $lon]);

        if (! $reponse->successful()) {
            return 0;
        }

        $jours = [];
        $couvert = null;
        foreach ((array) $reponse->json('properties.timeseries') as $instant) {
            $moment = Carbon::parse($instant['time'])->timezone(config('app.timezone'));
            $jour = $moment->toDateString();
            $details = $instant['data']['instant']['details'] ?? [];
            $j = $jours[$jour] ?? ['min' => null, 'max' => null, 'pluie' => 0.0, 'vent' => 0.0, 'symbole' => null, 'ecart' => 99];

            if (isset($details['air_temperature'])) {
                $t = (float) $details['air_temperature'];
                $j['min'] = $j['min'] === null ? $t : min($j['min'], $t);
                $j['max'] = $j['max'] === null ? $t : max($j['max'], $t);
            }
            $j['vent'] = max($j['vent'], (float) ($details['wind_speed'] ?? 0) * 3.6);

            // Pluie : par heure au début, puis par 6 heures (sans compter deux fois la même période).
            $suite = $instant['data']['next_1_hours'] ?? $instant['data']['next_6_hours'] ?? null;
            if ($suite && ($couvert === null || $moment->gte($couvert))) {
                $j['pluie'] += (float) ($suite['details']['precipitation_amount'] ?? 0);
                $couvert = $moment->copy()->addHours(isset($instant['data']['next_1_hours']) ? 1 : 6);
            }
            // Symbole du milieu de journée (le plus proche de 12 h).
            $ecart = abs($moment->hour - 12);
            if ($suite && $ecart < $j['ecart']) {
                $j['symbole'] = $suite['summary']['symbol_code'] ?? null;
                $j['ecart'] = $ecart;
            }
            $jours[$jour] = $j;
        }

        $total = 0;
        foreach (array_slice($jours, 0, self::JOURS, true) as $jour => $j) {
            MeteoPrevision::updateOrCreate(['point' => $point, 'jour' => $jour], [
                'symbole' => $j['symbole'],
                'temperature_min' => $j['min'] !== null ? (int) round($j['min']) : null,
                'temperature_max' => $j['max'] !== null ? (int) round($j['max']) : null,
                'pluie_dixiemes_mm' => (int) round($j['pluie'] * 10),
                'vent_max_kmh' => (int) round($j['vent']),
            ]);
            $total++;
        }

        return $total;
    }

    /**
     * Prévisions déjà en cache pour un rendez-vous (aucun appel réseau).
     *
     * @return Collection<string, MeteoPrevision> par jour (AAAA-MM-JJ)
     */
    public function pour(RendezVous $rdv): Collection
    {
        $adresse = $rdv->adresse();
        $lieu = $adresse ? $this->situer($adresse) : null;
        if (! $lieu) {
            return collect();
        }

        return MeteoPrevision::where('point', self::point((float) $lieu->latitude, (float) $lieu->longitude))
            ->whereBetween('jour', [$rdv->debut->toDateString(), $rdv->fin->toDateString()])
            ->get()
            ->keyBy(fn (MeteoPrevision $p) => substr((string) $p->jour, 0, 10));
    }

    private function userAgent(): string
    {
        // MET Norway demande un User-Agent qui identifie l'application et un contact.
        $contact = (string) reglage('identite.site') ?: (string) reglage('identite.email');

        return 'AppDevisFactures/1.0'.($contact ? ' ('.$contact.')' : ' (+'.config('app.url').')');
    }
}
