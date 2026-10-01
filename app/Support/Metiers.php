<?php

namespace App\Support;

/**
 * Métiers proposés au premier lancement : catalogue de départ (SANS prix :
 * chaque entreprise saisit ses propres tarifs), clauses de CGV propres au
 * métier, et modules conseillés.
 */
class Metiers
{
    public const MODULES = [
        'calculateur_toiture' => 'Calculateur de surface de toiture (selon la pente)',
        'photos' => 'Photos avant / après des chantiers',
        'meteo' => 'Météo des chantiers (pluie, vent, gel)',
        'rapports' => 'Rapports d\'intervention',
        'entretien' => 'Rappels d\'entretien périodique',
    ];

    /**
     * @return array<string, array{libelle: string, modules: list<string>, clauses: string, catalogue: list<array{categorie: string, nom: string, unite: string}>}>
     */
    public static function tous(): array
    {
        return [
            'couvreur' => [
                'libelle' => 'Couvreur – zingueur',
                'modules' => ['calculateur_toiture', 'photos', 'meteo', 'rapports', 'entretien'],
                'clauses' => "Article complémentaire — Travaux de toiture\nLes interventions en toiture dépendent des conditions météorologiques. Le client autorise l'installation des protections et de l'échafaudage nécessaires. Les éléments de couverture découverts en mauvais état non visibles lors de la visite (voliges, chevrons…) feront l'objet d'un devis complémentaire.",
                'catalogue' => [
                    ['categorie' => 'Entretien', 'nom' => 'Démoussage de toiture', 'unite' => 'm²'],
                    ['categorie' => 'Entretien', 'nom' => 'Traitement hydrofuge', 'unite' => 'm²'],
                    ['categorie' => 'Entretien', 'nom' => 'Nettoyage des gouttières', 'unite' => 'ml'],
                    ['categorie' => 'Couverture', 'nom' => 'Remplacement de tuiles', 'unite' => 'u'],
                    ['categorie' => 'Couverture', 'nom' => 'Réfection de couverture en tuiles', 'unite' => 'm²'],
                    ['categorie' => 'Couverture', 'nom' => 'Réfection de couverture en ardoises', 'unite' => 'm²'],
                    ['categorie' => 'Couverture', 'nom' => 'Pose d\'écran sous toiture', 'unite' => 'm²'],
                    ['categorie' => 'Faîtage', 'nom' => 'Faîtière scellée', 'unite' => 'u'],
                    ['categorie' => 'Faîtage', 'nom' => 'Faîtage à sec ventilé', 'unite' => 'ml'],
                    ['categorie' => 'Zinguerie', 'nom' => 'Gouttière zinc', 'unite' => 'ml'],
                    ['categorie' => 'Zinguerie', 'nom' => 'Descente d\'eau pluviale', 'unite' => 'ml'],
                    ['categorie' => 'Zinguerie', 'nom' => 'Abergement de cheminée', 'unite' => 'forfait'],
                    ['categorie' => 'Charpente', 'nom' => 'Remplacement de chevrons', 'unite' => 'ml'],
                    ['categorie' => 'Fenêtres de toit', 'nom' => 'Pose de fenêtre de toit', 'unite' => 'u'],
                    ['categorie' => 'Chantier', 'nom' => 'Échafaudage', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Évacuation des déchets', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Recherche de fuite', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'macon' => [
                'libelle' => 'Maçon',
                'modules' => ['photos', 'meteo', 'rapports'],
                'clauses' => "Article complémentaire — Travaux de maçonnerie\nLes temps de séchage des bétons et enduits dépendent de la température et de l'humidité. Le client signale avant le début des travaux la présence de réseaux enterrés connus.",
                'catalogue' => [
                    ['categorie' => 'Gros œuvre', 'nom' => 'Mur en parpaings', 'unite' => 'm²'],
                    ['categorie' => 'Gros œuvre', 'nom' => 'Dalle béton', 'unite' => 'm²'],
                    ['categorie' => 'Gros œuvre', 'nom' => 'Ouverture dans un mur porteur', 'unite' => 'forfait'],
                    ['categorie' => 'Façade', 'nom' => 'Enduit de façade', 'unite' => 'm²'],
                    ['categorie' => 'Façade', 'nom' => 'Rejointoiement', 'unite' => 'm²'],
                    ['categorie' => 'Chantier', 'nom' => 'Évacuation des gravats', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'plombier' => [
                'libelle' => 'Plombier – chauffagiste',
                'modules' => ['photos', 'rapports', 'entretien'],
                'clauses' => "Article complémentaire — Plomberie et chauffage\nLe client permet l'accès aux compteurs et vannes d'arrêt. Les appareils fournis par le client ne sont pas garantis par l'Entreprise.",
                'catalogue' => [
                    ['categorie' => 'Dépannage', 'nom' => 'Déplacement', 'unite' => 'forfait'],
                    ['categorie' => 'Dépannage', 'nom' => 'Recherche de fuite', 'unite' => 'forfait'],
                    ['categorie' => 'Sanitaire', 'nom' => 'Remplacement de robinetterie', 'unite' => 'u'],
                    ['categorie' => 'Sanitaire', 'nom' => 'Pose de WC', 'unite' => 'u'],
                    ['categorie' => 'Chauffage', 'nom' => 'Entretien de chaudière', 'unite' => 'forfait'],
                    ['categorie' => 'Chauffage', 'nom' => 'Pose de radiateur', 'unite' => 'u'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'electricien' => [
                'libelle' => 'Électricien',
                'modules' => ['photos', 'rapports'],
                'clauses' => "Article complémentaire — Électricité\nLes travaux sont réalisés selon la norme NF C 15-100 en vigueur. La mise en conformité des parties existantes non visées au devis n'est pas comprise.",
                'catalogue' => [
                    ['categorie' => 'Installation', 'nom' => 'Point lumineux', 'unite' => 'u'],
                    ['categorie' => 'Installation', 'nom' => 'Prise de courant', 'unite' => 'u'],
                    ['categorie' => 'Tableau', 'nom' => 'Remplacement de tableau électrique', 'unite' => 'forfait'],
                    ['categorie' => 'Dépannage', 'nom' => 'Déplacement', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'peintre' => [
                'libelle' => 'Peintre',
                'modules' => ['photos', 'rapports'],
                'clauses' => "Article complémentaire — Peinture\nLes pièces sont libérées du mobilier par le client, sauf mention contraire au devis. Les teintes sont validées par le client sur nuancier avant application.",
                'catalogue' => [
                    ['categorie' => 'Préparation', 'nom' => 'Protection des sols et meubles', 'unite' => 'forfait'],
                    ['categorie' => 'Préparation', 'nom' => 'Rebouchage et ponçage', 'unite' => 'm²'],
                    ['categorie' => 'Peinture', 'nom' => 'Peinture des murs (2 couches)', 'unite' => 'm²'],
                    ['categorie' => 'Peinture', 'nom' => 'Peinture des plafonds (2 couches)', 'unite' => 'm²'],
                    ['categorie' => 'Peinture', 'nom' => 'Peinture des boiseries', 'unite' => 'ml'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'menuisier' => [
                'libelle' => 'Menuisier',
                'modules' => ['photos', 'rapports'],
                'clauses' => "Article complémentaire — Menuiserie\nLes cotes sont prises par l'Entreprise avant fabrication. Les menuiseries sur mesure ne peuvent être ni reprises ni échangées une fois commandées.",
                'catalogue' => [
                    ['categorie' => 'Pose', 'nom' => 'Pose de fenêtre', 'unite' => 'u'],
                    ['categorie' => 'Pose', 'nom' => 'Pose de porte intérieure', 'unite' => 'u'],
                    ['categorie' => 'Pose', 'nom' => 'Pose de volet roulant', 'unite' => 'u'],
                    ['categorie' => 'Agencement', 'nom' => 'Placard sur mesure', 'unite' => 'ml'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
            'multiservice' => [
                'libelle' => 'Multiservice – rénovation',
                'modules' => ['photos', 'meteo', 'rapports', 'entretien'],
                'clauses' => '',
                'catalogue' => [
                    ['categorie' => 'Petits travaux', 'nom' => 'Déplacement', 'unite' => 'forfait'],
                    ['categorie' => 'Petits travaux', 'nom' => 'Petite réparation', 'unite' => 'forfait'],
                    ['categorie' => 'Rénovation', 'nom' => 'Pose de carrelage', 'unite' => 'm²'],
                    ['categorie' => 'Rénovation', 'nom' => 'Pose de parquet', 'unite' => 'm²'],
                    ['categorie' => 'Chantier', 'nom' => 'Évacuation des déchets', 'unite' => 'forfait'],
                    ['categorie' => 'Chantier', 'nom' => 'Main-d\'œuvre', 'unite' => 'h'],
                ],
            ],
        ];
    }

    /**
     * @return array{libelle: string, modules: list<string>, clauses: string, catalogue: list<array{categorie: string, nom: string, unite: string}>}|null
     */
    public static function metier(string $cle): ?array
    {
        return self::tous()[$cle] ?? null;
    }

    public static function cgvDeDepart(string $cle): string
    {
        $cgv = trim((string) file_get_contents(resource_path('metiers/cgv-base.txt')));
        $clauses = self::metier($cle)['clauses'] ?? '';

        return $clauses !== '' ? $cgv."\n\n".$clauses : $cgv;
    }

    public static function moduleActif(string $module): bool
    {
        return in_array($module, (array) reglage('modules.actifs', []), true);
    }
}
