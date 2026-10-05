<?php

namespace App\Support;

/**
 * Outils de couleur : contraste (WCAG) et variantes pour le mode sombre.
 */
class Couleurs
{
    /**
     * @return array{int, int, int}
     */
    public static function rvb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(int $r, int $v, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $v)), max(0, min(255, $b)));
    }

    public static function luminance(string $hex): float
    {
        $canaux = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rvb($hex));

        return 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];
    }

    public static function contraste(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Texte blanc ou foncé : celui qui se lit le mieux sur ce fond.
     */
    public static function texteSur(string $fond): string
    {
        return self::contraste($fond, '#ffffff') >= self::contraste($fond, '#1b1d21') ? '#ffffff' : '#1b1d21';
    }

    /**
     * Mélange avec du blanc (0 à 1) : version claire pour le mode sombre.
     */
    public static function eclaircir(string $hex, float $part): string
    {
        [$r, $v, $b] = self::rvb($hex);

        return self::hex(
            (int) round($r + (255 - $r) * $part),
            (int) round($v + (255 - $v) * $part),
            (int) round($b + (255 - $b) * $part),
        );
    }

    /** Fonds du mode sombre où la couleur est utilisée : carte, page, champ. */
    public const FONDS_SOMBRES = ['#1a2229', '#11171c', '#212b33'];

    /** Fonds du mode clair : carte, page. */
    public const FONDS_CLAIRS = ['#ffffff', '#eef1f3'];

    /**
     * Mélange deux couleurs ($part de la première).
     */
    public static function melanger(string $a, string $b, float $part): string
    {
        [$r1, $v1, $b1] = self::rvb($a);
        [$r2, $v2, $b2] = self::rvb($b);

        return self::hex(
            (int) round($r1 * $part + $r2 * (1 - $part)),
            (int) round($v1 * $part + $v2 * (1 - $part)),
            (int) round($b1 * $part + $b2 * (1 - $part)),
        );
    }

    /**
     * Couleurs du thème calculées depuis les réglages : barre du haut, boutons, liens et
     * encadrés, en clair et en sombre. Les couleurs de texte sont toujours lisibles (≥ 4,5).
     *
     * @return array{clair: array<string, string>, sombre: array<string, string>}
     */
    public static function theme(string $principale, string $accent): array
    {
        $infoClair = self::melanger($accent, '#ffffff', 0.12);
        $infoSombre = self::melanger($accent, '#1a2229', 0.2);

        return [
            'clair' => [
                '--barre-haut' => $principale,
                '--barre-haut-texte' => self::texteSur($principale),
                '--couleur-principale' => self::pourModeClair($principale, [...self::FONDS_CLAIRS, $infoClair]),
                '--couleur-accent' => $accent,
                '--couleur-accent-texte' => self::texteSur($accent),
                '--couleur-active' => self::pourModeClair($accent, [...self::FONDS_CLAIRS, $infoClair]),
                '--info-fond' => $infoClair,
            ],
            'sombre' => [
                '--barre-haut' => '#0c1115',
                '--barre-haut-texte' => '#ffffff',
                '--couleur-principale' => self::pourModeSombre($principale, [...self::FONDS_SOMBRES, $infoSombre]),
                '--couleur-accent' => $accent,
                '--couleur-accent-texte' => self::texteSur($accent),
                '--couleur-active' => self::pourModeSombre($accent, [...self::FONDS_SOMBRES, $infoSombre]),
                '--info-fond' => $infoSombre,
            ],
        ];
    }

    /**
     * Contraste le plus faible de la couleur sur une liste de fonds.
     *
     * @param  list<string>  $fonds
     */
    public static function contrasteMinimum(string $couleur, array $fonds): float
    {
        return min(array_map(fn (string $fond) => self::contraste($couleur, $fond), $fonds));
    }

    /**
     * Version assez claire pour être lisible sur tous les fonds sombres (contraste ≥ 4,5).
     *
     * @param  string|list<string>  $fondsSombres
     */
    public static function pourModeSombre(string $hex, string|array $fondsSombres = self::FONDS_SOMBRES): string
    {
        $fonds = (array) $fondsSombres;
        $couleur = $hex;
        for ($part = 0.0; $part <= 1.0 && self::contrasteMinimum($couleur, $fonds) < 4.5; $part += 0.02) {
            $couleur = self::eclaircir($hex, $part);
        }

        return $couleur;
    }

    /**
     * Version assez foncée pour être lisible sur tous les fonds clairs (contraste ≥ 4,5).
     *
     * @param  string|list<string>  $fondsClairs
     */
    public static function pourModeClair(string $hex, string|array $fondsClairs = self::FONDS_CLAIRS): string
    {
        $fonds = (array) $fondsClairs;
        [$r, $v, $b] = self::rvb($hex);
        $couleur = $hex;
        for ($part = 0.0; $part <= 1.0 && self::contrasteMinimum($couleur, $fonds) < 4.5; $part += 0.02) {
            $couleur = self::hex((int) round($r * (1 - $part)), (int) round($v * (1 - $part)), (int) round($b * (1 - $part)));
        }

        return $couleur;
    }
}
