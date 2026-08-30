<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\Request;

class ParametersController extends Controller
{
    /**
     * Get the parameter with 'Success' status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuccessParameter()
    {
        $parameter = Parameter::where('status', 'Success')->first();

        if ($parameter) {
            return response()->json([
                'success' => true,
                'data' => $parameter,
                /*
                 | Le point de retrait désigné, sous une forme lisible.
                 |
                 | L'application affichait « Coordonnées non disponibles » au
                 | client qui n'avait pas choisi de lieu, et envoyait ce texte
                 | comme adresse de livraison. Elle a maintenant de quoi nommer
                 | le lieu de repli, celui-là même que le serveur retient déjà
                 | pour les coordonnées.
                 */
                'point_de_retrait' => app(\App\Support\PointDeLivraison::class)->nomDuLieuParDefaut(),
                /*
                 | Tarifs par service, tels qu'ils s'appliquent à cet instant
                 | — la plage horaire courante de chaque grille.
                 |
                 | Ajoutés à côté des champs plats de 'data', jamais à leur
                 | place : ces champs sont lus par les trois applications
                 | mobiles (CLAUDE.md règle 1) et les retirer les casserait
                 | toutes d'un coup. Une application qui ignore 'tarifs'
                 | continue donc de fonctionner exactement comme avant.
                 |
                 | Un service sans grille vaut null : l'application garde alors
                 | les champs plats qu'elle lit déjà, sans avoir à distinguer
                 | les deux cas.
                 */
                'tarifs' => [
                    'clando' => app(\App\Support\GrilleTarifaire::class)
                        ->pourApplication(\App\Models\Tarif::CLANDO),
                    'livraison' => app(\App\Support\GrilleTarifaire::class)
                        ->pourApplication(\App\Models\Tarif::LIVRAISON),
                    'coursier' => app(\App\Support\GrilleTarifaire::class)
                        ->pourApplication(\App\Models\Tarif::COURSIER),
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No parameter with Success status found.',
        ], 404);
    }
}