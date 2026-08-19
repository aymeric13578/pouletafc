<?php

namespace App\Support;

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Où livrer une commande.
 *
 * La création de commande copiait users.latitude/longitude — la dernière
 * position connue du téléphone du client, écrite une fois et quasiment jamais
 * remise à jour. L'adresse choisie au panier n'était conservée qu'en texte, et
 * ses coordonnées jetées. Résultat mesuré en production : 65 clients sur 74
 * avaient toujours exactement le même point de livraison, quelle que soit
 * l'adresse sélectionnée.
 *
 * Les 179 lieux enregistrés par les agents portent pourtant leurs coordonnées,
 * et c'est cette liste qui alimente la recherche d'adresse du panier.
 *
 * D'où quatre sources, de la plus fiable à la moins fiable :
 *
 *  1. les coordonnées transmises explicitement par l'application ;
 *  2. le lieu désigné par son identifiant ;
 *  3. le lieu retrouvé par son nom, à partir de l'adresse choisie — c'est ce qui
 *     répare le cas courant sans rien changer à l'application ;
 *  4. la position du téléphone, en dernier recours seulement.
 */
class PointDeLivraison
{
    /*
     | Limites du Cameroun, un peu élargies.
     |
     | Elles ne servent qu'à repérer un point manifestement faux : 45 commandes
     | en base portent une latitude et une longitude interverties, ce qui place
     | la livraison à 13,4 N / 9,3 E, en plein Niger, au lieu de Garoua.
     |
     | Elles collent aux limites réelles du pays, sans marge de confort. Prises
     | plus larges — latitude jusqu'à 13,5, longitude dès 8 — le point inverti
     | passait lui aussi pour valide et l'inversion n'était jamais repérée.
     */
    private const LAT_MIN = 1.6;
    private const LAT_MAX = 13.1;
    private const LON_MIN = 8.4;
    private const LON_MAX = 16.2;

    /**
     * @return array{0: float|null, 1: float|null, 2: string} latitude, longitude, origine
     */
    public function resoudre(Request $request, ?User $user): array
    {
        foreach ($this->candidats($request, $user) as $origine => $point) {
            [$lat, $lon] = $point;

            if ($lat === null || $lon === null) {
                continue;
            }

            [$lat, $lon] = $this->redresser($lat, $lon, $origine);

            if ($this->dansLeCameroun($lat, $lon)) {
                return [$lat, $lon, $origine];
            }

            /*
             | Un point hors zone qu'on ne sait pas redresser n'est pas retenu :
             | on passe à la source suivante plutôt que d'envoyer un livreur à
             | mille kilomètres. Tracé, car c'est le signe d'une saisie fausse en
             | amont qu'il faudra corriger à la source.
             */
            Log::warning('Point de livraison hors zone ignoré', [
                'origine' => $origine,
                'lat' => $lat,
                'lon' => $lon,
            ]);
        }

        return [null, null, 'aucune'];
    }

    /**
     * Sources possibles, dans l'ordre de confiance.
     *
     * @return array<string, array{0: float|null, 1: float|null}>
     */
    private function candidats(Request $request, ?User $user): array
    {
        return [
            'transmis' => [
                $this->nombre($request->input('delivery_lat')),
                $this->nombre($request->input('delivery_lon')),
            ],
            'lieu_id' => $this->lieuParId($request->input('id_location')),
            'lieu_nom' => $this->lieuParNom($request->input('delivery_address')),
            'position_client' => [
                $this->nombre($user?->latitude),
                $this->nombre($user?->longitude),
            ],
            /*
             | Dernier recours : le lieu de retrait désigné par l'administration.
             |
             | Il vient après la position du compte, non avant : quand le client
             | a une position connue, elle reste plus proche de la vérité qu'un
             | point unique valable pour toute la ville. Mais elle manque
             | souvent — un compte jamais localisé porte des coordonnées vides ou
             | héritées — et c'est alors ce lieu qui évite d'envoyer le livreur
             | nulle part, ou pire, toujours au même endroit faux.
             */
            'lieu_par_defaut' => $this->lieuParDefaut(),
        ];
    }

    /**
     * Textes que l'application envoie faute d'adresse choisie.
     *
     * Ce ne sont pas des adresses : ce sont des messages d'erreur qui se sont
     * retrouvés enregistrés à la place. Le mur du comptoir affichait donc
     * « Coordonnées non disponibles » en guise de lieu de livraison, et le
     * livreur n'avait rien pour se repérer.
     */
    private const NON_ADRESSES = [
        'coordonnées non disponibles',
        'coordonnees non disponibles',
        'adresse inconnue',
        "erreur lors de la récupération de l'adresse",
        'null',
        '-',
    ];

    /**
     * L'adresse à enregistrer sur la commande.
     *
     * Quand le client n'en a pas choisi, on retient le nom du point de retrait
     * désigné dans l'administration — celui-là même qui sert déjà de dernier
     * recours pour les coordonnées. Les deux disaient jusqu'ici des choses
     * différentes : le livreur recevait le bon point sur la carte, et un message
     * d'erreur en guise d'adresse.
     */
    public function adresse(?string $recue): ?string
    {
        $recue = trim((string) $recue);

        if ($recue !== '' && ! in_array(mb_strtolower($recue), self::NON_ADRESSES, true)) {
            return $recue;
        }

        return $this->nomDuLieuParDefaut() ?? ($recue !== '' ? $recue : null);
    }

    /**
     * Nom du point de retrait désigné, s'il en existe un.
     */
    public function nomDuLieuParDefaut(): ?string
    {
        $idLieu = \App\Models\Parameter::active()?->default_pickup_location_id;

        if (! $idLieu) {
            return null;
        }

        $lieu = \App\Models\Location::find($idLieu);

        if (! $lieu) {
            return null;
        }

        // Le quartier avec le lieu : « Marché central » seul ne suffit pas à
        // situer une livraison quand plusieurs quartiers en ont un.
        $quartier = $lieu->quarter?->name;

        return $quartier ? $lieu->name . ' — ' . $quartier : $lieu->name;
    }

    /**
     * Lieu de retrait désigné dans la configuration active.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function lieuParDefaut(): array
    {
        $idLieu = \App\Models\Parameter::active()?->default_pickup_location_id;

        if (! $idLieu) {
            return [null, null];
        }

        // Le lieu a pu être supprimé depuis sa désignation : on ne suppose pas
        // qu'il existe encore.
        $lieu = \App\Models\Location::find($idLieu);

        return [$this->nombre($lieu?->latitude), $this->nombre($lieu?->longitude)];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function lieuParId($id): array
    {
        if (! $id || ! is_numeric($id)) {
            return [null, null];
        }

        $lieu = Location::find((int) $id);

        return [$this->nombre($lieu?->latitude), $this->nombre($lieu?->longitude)];
    }

    /**
     * Lieu retrouvé à partir du texte de l'adresse choisie.
     *
     * C'est la recherche d'adresse du panier qui propose ces noms : celui que
     * l'application renvoie correspond donc presque toujours à une ligne de la
     * table. Comparaison insensible à la casse et aux espaces de bordure, sans
     * chercher plus loin — une correspondance approximative livrerait au mauvais
     * endroit avec l'assurance d'être juste.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function lieuParNom($adresse): array
    {
        $adresse = is_string($adresse) ? trim($adresse) : '';

        if ($adresse === '') {
            return [null, null];
        }

        $lieu = Location::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($adresse)])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first();

        return [$this->nombre($lieu?->latitude), $this->nombre($lieu?->longitude)];
    }

    /**
     * Remet une latitude et une longitude interverties dans le bon ordre.
     *
     * Uniquement quand l'échange lève l'ambiguïté : le point d'origine tombe
     * hors du Cameroun et le point échangé y tombe. Dans tout autre cas on ne
     * touche à rien — corriger au jugé livrerait à un endroit inventé.
     *
     * @return array{0: float, 1: float}
     */
    private function redresser(float $lat, float $lon, string $origine): array
    {
        if ($this->dansLeCameroun($lat, $lon) || ! $this->dansLeCameroun($lon, $lat)) {
            return [$lat, $lon];
        }

        Log::info('Coordonnées de livraison interverties, remises dans l\'ordre', [
            'origine' => $origine,
            'avant' => ['lat' => $lat, 'lon' => $lon],
            'apres' => ['lat' => $lon, 'lon' => $lat],
        ]);

        return [$lon, $lat];
    }

    private function dansLeCameroun(float $lat, float $lon): bool
    {
        return $lat >= self::LAT_MIN && $lat <= self::LAT_MAX
            && $lon >= self::LON_MIN && $lon <= self::LON_MAX;
    }

    /**
     * Coordonnée exploitable, ou null.
     *
     * Le zéro est écarté : il tombe au large du golfe de Guinée et signifie en
     * pratique « rien de relevé ».
     */
    private function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        return $nombre === 0.0 ? null : $nombre;
    }
}
