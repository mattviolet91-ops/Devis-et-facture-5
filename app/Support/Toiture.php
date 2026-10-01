<?php

namespace App\Support;

/**
 * Calculateur de surface de toiture : la surface réelle (rampant) est plus grande
 * que la surface au sol, selon la pente. Surface rampant = surface au sol / cos(pente).
 */
class Toiture
{
    public static function degresDepuisPourcentage(float $pourcentage): float
    {
        return rad2deg(atan($pourcentage / 100));
    }

    /**
     * @return float Surface du rampant en m², arrondie au centième.
     */
    public static function surfaceRampant(float $surfaceAuSol, float $pente, string $unite = 'degres'): float
    {
        $degres = $unite === 'pourcentage' ? self::degresDepuisPourcentage($pente) : $pente;

        if ($degres < 0 || $degres >= 90) {
            throw new \InvalidArgumentException('La pente doit être comprise entre 0 et 90°.');
        }

        return round($surfaceAuSol / cos(deg2rad($degres)), 2);
    }
}
