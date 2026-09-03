<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Le résultat d'un calcul de prix : ce que le serveur s'engage à facturer
 * pour un service et une distance, à l'instant du calcul.
 *
 * Immuable et sans logique : la logique est dans Tarification, et ce qui
 * sort d'ici est directement sérialisé vers les applications mobiles.
 */
final class Devis
{
    public const SOURCE_GRILLE = 'grille';
    public const SOURCE_PARAMETERS = 'parameters';
    public const SOURCE_DEFAUT = 'defaut';

    public function __construct(
        public readonly string $service,
        public readonly float $distanceKm,
        public readonly bool $vip,
        public readonly int $prix,
        public readonly int $prixClassique,
        public readonly string $source,
        /** @var array<string, mixed> Le tarif appliqué, pour affichage/débogage. */
        public readonly array $tarif,
        public readonly CarbonImmutable $calculeA,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'distance_km' => $this->distanceKm,
            'type' => $this->vip ? 'vip' : 'classic',
            'prix' => $this->prix,
            'prix_classique' => $this->prixClassique,
            'devise' => 'XAF',
            'source' => $this->source,
            'tarif' => $this->tarif,
            'calcule_a' => $this->calculeA->toIso8601String(),
        ];
    }
}
