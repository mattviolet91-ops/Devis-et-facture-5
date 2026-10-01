<?php

namespace App\Support;

/**
 * « Astuce du jour » de l'accueil : une astuce différente chaque jour.
 */
class Astuces
{
    /** @var list<array{texte: string, route: string}> */
    public const LISTE = [
        ['texte' => 'Dictez un devis en une phrase avec le devis express : « Mme Martin, démoussage 120 m² à 12 € ».', 'route' => 'devis.express'],
        ['texte' => 'Faites signer un devis directement sur le téléphone, chez le client.', 'route' => 'devis.index'],
        ['texte' => 'Prenez des photos avant et après : elles peuvent aller en annexe du devis et dans le rapport.', 'route' => 'clients.index'],
        ['texte' => 'Les devis acceptés sans date apparaissent dans Planning → À planifier.', 'route' => 'planning.a-planifier'],
        ['texte' => 'Activez les notifications sur ce téléphone pour être prévenu d\'un devis signé ou d\'un paiement.', 'route' => 'plus'],
        ['texte' => 'Choisissez les blocs de l\'accueil et les boutons de la barre du bas : touchez « Personnaliser ».', 'route' => 'accueil.personnaliser'],
        ['texte' => 'Le suivi commercial liste les devis à relancer et les clients à qui proposer un entretien.', 'route' => 'suivi'],
    ];

    /**
     * @return array{texte: string, route: string}
     */
    public static function duJour(): array
    {
        return self::LISTE[(int) now()->dayOfYear % count(self::LISTE)];
    }
}
