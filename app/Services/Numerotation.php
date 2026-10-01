<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Numérotation continue et sans trou : DEV-AAAA-0001, FAC-AAAA-0001, AV-AAAA-0001.
 *
 * Le numéro est attribué au dernier moment (envoi du devis, émission de la facture),
 * à l'intérieur de la même transaction que l'enregistrement du document : si
 * l'enregistrement échoue, le numéro n'est pas consommé.
 */
class Numerotation
{
    public const TYPES = ['devis', 'facture', 'avoir'];

    /**
     * Attribue le numéro suivant. À appeler DANS une transaction.
     */
    public function attribuer(string $type, ?int $annee = null): string
    {
        $this->verifierType($type);
        $annee ??= (int) now()->format('Y');

        return DB::transaction(function () use ($type, $annee) {
            $compteur = DB::table('compteurs')
                ->where('type', $type)->where('annee', $annee)
                ->lockForUpdate()->first();

            if ($compteur) {
                $numero = $compteur->dernier + 1;
                DB::table('compteurs')->where('id', $compteur->id)->update(['dernier' => $numero, 'updated_at' => now()]);
            } else {
                $numero = $this->premier($type);
                DB::table('compteurs')->insert([
                    'type' => $type, 'annee' => $annee, 'dernier' => $numero,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $this->formater($type, $annee, $numero);
        });
    }

    /**
     * Numéro qui sera attribué au prochain document (sans le réserver).
     */
    public function prochain(string $type, ?int $annee = null): string
    {
        $this->verifierType($type);
        $annee ??= (int) now()->format('Y');

        $dernier = DB::table('compteurs')->where('type', $type)->where('annee', $annee)->value('dernier');

        return $this->formater($type, $annee, $dernier !== null ? $dernier + 1 : $this->premier($type));
    }

    /**
     * Le premier numéro ne peut plus changer une fois des numéros attribués cette année
     * (sinon on créerait un trou ou un doublon).
     */
    public function premierModifiable(string $type, ?int $annee = null): bool
    {
        $annee ??= (int) now()->format('Y');

        return ! DB::table('compteurs')->where('type', $type)->where('annee', $annee)->exists();
    }

    public function formater(string $type, int $annee, int $numero): string
    {
        $prefixe = (string) reglage("numerotation.{$type}_prefixe");

        return sprintf('%s-%d-%04d', $prefixe, $annee, $numero);
    }

    private function premier(string $type): int
    {
        return max(1, (int) reglage("numerotation.{$type}_premier", 1));
    }

    private function verifierType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Type de document inconnu : {$type}");
        }
    }
}
