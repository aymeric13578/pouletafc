<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Agent;
use App\Models\Location;

class GeolocalisationController extends Controller
{
     public function getLocationAgent()
     {
         
         $data = Agent::Where('in_activity',1)->select('actual_lat_position_agent','actual_lon_position_agent','agent_name')->get();
         
          return response()->json([
                "response"=>200,
                "data"=>$data,
                "lenght"=>count($data),
    
            ]);

     }

     /**
      * Liste des lieux de livraison enregistrés, avec leur quartier.
      *
      * L'application mobile appelait déjà cette route pour remplir la recherche
      * d'adresse du panier, mais elle n'avait jamais été implémentée côté
      * serveur : elle répondait 404, la liste restait donc vide et l'utilisateur
      * obtenait toujours « Aucun résultat ».
      *
      * Le client attend pour chaque élément : id, name, latitude, longitude et
      * un objet quarter facultatif contenant name.
      */
     public function getLocations()
     {
         $data = Location::with('quarter:id,name')
             ->select('id', 'id_quarter', 'name', 'latitude', 'longitude')
             // Sans coordonnées exploitables, le lieu est inutilisable et
             // fausserait le calcul des frais de livraison.
             ->whereNotNull('latitude')
             ->whereNotNull('longitude')
             ->whereNotIn('latitude', ['', '0'])
             ->whereNotIn('longitude', ['', '0'])
             ->orderBy('name')
             ->get();

         return response()->json([
             "response" => 200,
             "data" => $data,
             "lenght" => count($data),
         ]);
     }
}
