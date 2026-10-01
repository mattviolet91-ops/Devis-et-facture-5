<?php

namespace App\Support;

use App\Models\RendezVous;
use Illuminate\Support\Collection;

/**
 * Fichier .ics (format iCalendar) à ouvrir dans l'agenda du téléphone.
 */
class Calendrier
{
    /**
     * @param  Collection<int, RendezVous>  $rendezVous
     */
    public static function ics(Collection $rendezVous): string
    {
        $hote = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Application artisan//Planning//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($rendezVous as $rdv) {
            $lignes[] = 'BEGIN:VEVENT';
            $lignes[] = 'UID:rdv-'.$rdv->id.'@'.$hote;
            $lignes[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            if ($rdv->journee_entiere) {
                $lignes[] = 'DTSTART;VALUE=DATE:'.$rdv->debut->format('Ymd');
                $lignes[] = 'DTEND;VALUE=DATE:'.$rdv->fin->copy()->addDay()->format('Ymd');
            } else {
                $lignes[] = 'DTSTART:'.$rdv->debut->copy()->utc()->format('Ymd\THis\Z');
                $lignes[] = 'DTEND:'.$rdv->fin->copy()->utc()->format('Ymd\THis\Z');
            }
            $lignes[] = 'SUMMARY:'.self::texte($rdv->titre.($rdv->client ? ' – '.$rdv->client->nomComplet() : ''));
            if ($rdv->adresse()) {
                $lignes[] = 'LOCATION:'.self::texte((string) $rdv->adresse());
            }
            $description = trim(implode("\n", array_filter([
                $rdv->client?->telephone ? 'Téléphone : '.$rdv->client->telephone : null,
                $rdv->notes,
            ])));
            if ($description !== '') {
                $lignes[] = 'DESCRIPTION:'.self::texte($description);
            }
            $lignes[] = 'END:VEVENT';
        }
        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::plier(...), $lignes))."\r\n";
    }

    private static function texte(string $texte): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $texte);
    }

    /**
     * Lignes de 75 octets au plus (règle du format), sans couper un caractère.
     */
    private static function plier(string $ligne): string
    {
        $resultat = '';
        $courante = '';
        foreach (mb_str_split($ligne) as $caractere) {
            if (strlen($courante.$caractere) > 74) {
                $resultat .= $courante."\r\n ";
                $courante = '';
            }
            $courante .= $caractere;
        }

        return $resultat.$courante;
    }
}
