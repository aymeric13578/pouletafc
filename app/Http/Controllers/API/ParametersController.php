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
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No parameter with Success status found.',
        ], 404);
    }
}