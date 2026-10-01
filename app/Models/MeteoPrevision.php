<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MeteoPrevision extends Model
{
    protected $table = 'meteo_previsions';

    protected $fillable = ['point', 'jour', 'symbole', 'temperature_min', 'temperature_max', 'pluie_dixiemes_mm', 'vent_max_kmh'];

    /**
     * Le jour reste une simple chaîne « AAAA-MM-JJ » (même format en base SQLite et MariaDB).
     */
    public function date(): Carbon
    {
        return Carbon::parse(substr((string) $this->jour, 0, 10));
    }

    /** Seuils des alertes : pluie (mm), vent fort (km/h), gel (°C). */
    public const SEUIL_PLUIE_DIXIEMES = 20;

    public const SEUIL_VENT_KMH = 50;

    /**
     * @return list<string>
     */
    public function alertes(): array
    {
        $alertes = [];
        if ($this->pluie_dixiemes_mm >= self::SEUIL_PLUIE_DIXIEMES) {
            $alertes[] = 'Pluie ('.number_format($this->pluie_dixiemes_mm / 10, 1, ',', '').' mm)';
        }
        if ($this->vent_max_kmh >= self::SEUIL_VENT_KMH) {
            $alertes[] = 'Vent fort ('.$this->vent_max_kmh.' km/h)';
        }
        if ($this->temperature_min !== null && $this->temperature_min <= 0) {
            $alertes[] = 'Gel ('.$this->temperature_min.' °C)';
        }

        return $alertes;
    }

    public function icone(): string
    {
        $s = (string) $this->symbole;

        return match (true) {
            str_contains($s, 'thunder') => '⛈',
            str_contains($s, 'snow') || str_contains($s, 'sleet') => '❄',
            str_contains($s, 'rain') => '🌧',
            str_contains($s, 'fog') => '🌫',
            str_contains($s, 'partlycloudy') || str_contains($s, 'fair') => '⛅',
            str_contains($s, 'cloudy') => '☁',
            str_contains($s, 'clearsky') => '☀',
            default => '·',
        };
    }

    public function resume(): string
    {
        return trim($this->icone().' '.($this->temperature_min !== null ? $this->temperature_min.'° / '.$this->temperature_max.'°' : ''));
    }
}
