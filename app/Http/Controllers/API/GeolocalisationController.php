<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Agent;
use App\Models\Location;
use App\Models\Quarter;

class GeolocalisationController extends Controller
{
     /**
      * Le message d'erreur est réaffiché tel quel dans l'application coursier :
      * les libellés par défaut de Laravel, en anglais et nommés d'après la
      * colonne ("The id quarter field..."), n'aideraient pas l'agent sur le
      * terrain.
      */
     private const MESSAGES_VALIDATION = [
         'name.required' => 'Le nom est obligatoire',
         'name.max' => 'Le nom est trop long (191 caractères maximum)',
         'id_user.required' => 'Utilisateur non identifié, reconnectez-vous',
         'id_user.exists' => 'Utilisateur non identifié, reconnectez-vous',
         'id_quarter.required' => 'Le quartier est obligatoire',
         'id_quarter.exists' => "Ce quartier n'existe plus",
         'latitude.required' => 'Position introuvable, activez la localisation',
         'longitude.required' => 'Position introuvable, activez la localisation',
         'latitude.numeric' => 'Position invalide',
         'longitude.numeric' => 'Position invalide',
         'latitude.between' => 'Position invalide',
         'longitude.between' => 'Position invalide',
         'type.max' => 'Catégorie invalide',
     ];

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
      * Position d'un agent, transmise en continu tant qu'il est en service.
      *
      * Jusqu'ici la position n'était écrite qu'une fois, au démarrage de la
      * journée par takeDay, puis plus jamais hors d'une course — et seulement
      * depuis l'écran de la course. Un agent en service mais sans course restait
      * donc figé à son point du matin sur les cartes, parfois pendant des
      * semaines : trois agents « en service » portaient des positions datant du
      * 4 juillet, du 5 juillet et du 5 août.
      *
      * L'horodatage accompagne le point : sans lui, les cartes ne peuvent pas
      * distinguer un relevé de dix secondes d'un relevé de six semaines.
      */
     public function updateAgentPosition(Request $request)
     {
         $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
         if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
             return $utilisateur;
         }

         $lat = $request->input('lat', $request->input('latitude'));
         $lon = $request->input('lon', $request->input('longitude'));

         if (! is_numeric($lat) || ! is_numeric($lon)) {
             return response()->json([
                 'response' => 400,
                 'message' => 'Identifiant ou coordonnées manquants',
             ]);
         }

         // Un zéro tombe au large du golfe de Guinée : c'est « rien de relevé »,
         // pas une position, et l'écrire ferait sauter le marqueur sur la carte.
         if ((float) $lat === 0.0 || (float) $lon === 0.0) {
             return response()->json(['response' => 400, 'message' => 'Coordonnées nulles']);
         }

         /*
          | La position écrite est toujours celle de l'appelant authentifié —
          | jamais celle d'un id_user fourni par le client (spec 2026-09-01,
          | §4 : le paramètre reste accepté pour compatibilité, mais n'a plus
          | aucun effet sur l'identité).
          */
         $modifiees = User::where('id', $utilisateur->id)->update([
             'actual_lat_position_agent' => (float) $lat,
             'actual_lon_position_agent' => (float) $lon,
             'position_updated_at' => now(),
         ]);

         return response()->json(['response' => $modifiees ? 200 : 404]);
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

     /**
      * Liste des quartiers avec les lieux qui leur sont rattachés.
      *
      * Alimente l'écran « Enregistrement » de l'application coursier : chaque
      * quartier y est une carte dépliable sur ses lieux. Le client lit
      * quarter['locations'], la relation est donc toujours renvoyée, même vide.
      */
     public function getQuarters()
     {
         $data = Quarter::with(['locations' => function ($requete) {
                 $requete->select('id', 'id_quarter', 'name', 'type', 'status', 'latitude', 'longitude', 'created_at')
                     ->orderBy('name');
             }])
             ->select('id', 'name', 'status', 'id_user', 'created_at')
             ->orderBy('name')
             ->get();

         return response()->json([
             "response" => 200,
             "data" => $data,
             "lenght" => count($data),
         ]);
     }

     /**
      * Création d'un quartier depuis l'application coursier.
      *
      * L'application n'envoie pas d'en-tête Accept : une ValidationException
      * lèverait une redirection HTML que le client ne sait pas décoder. On
      * valide donc à la main et on répond toujours en JSON, avec le code métier
      * dans "response" comme le reste de l'API.
      */
     public function createQuarter(Request $request)
     {
         $validation = Validator::make($request->all(), [
             'name' => 'required|string|max:191',
             'id_user' => 'required|exists:users,id',
         ], self::MESSAGES_VALIDATION);

         if ($validation->fails()) {
             return response()->json([
                 "response" => 400,
                 "message" => $validation->errors()->first(),
             ]);
         }

         $nom = trim($request->input('name'));

         // Les agents saisissent le même quartier depuis le terrain sans savoir
         // qu'un collègue l'a déjà créé : sans ce garde-fou, la liste se remplit
         // de doublons et les lieux se répartissent entre eux.
         $existant = Quarter::whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])->first();

         if ($existant) {
             return response()->json([
                 "response" => 409,
                 "message" => "Le quartier \"{$existant->name}\" existe déjà",
                 "data" => $existant,
             ]);
         }

         $quartier = Quarter::create([
             'name' => $nom,
             // La colonne est un enum sans valeur par défaut : l'omettre fait
             // échouer l'insertion. 'pending' est la valeur portée par la
             // totalité des lignes existantes, on ne dévie pas de l'existant.
             'status' => 'pending',
             'id_user' => $request->input('id_user'),
         ]);

         return response()->json([
             "response" => 200,
             "message" => "Quartier enregistré avec succès",
             "data" => $quartier,
         ]);
     }

     /**
      * Enregistrement d'un lieu de livraison, géolocalisé par le coursier.
      *
      * Les colonnes latitude/longitude sont des varchar en production : on
      * conserve la valeur telle quelle après validation numérique, sans
      * arrondi, pour ne pas dégrader la précision relevée sur le terrain.
      */
     public function createLocation(Request $request)
     {
         $validation = Validator::make($request->all(), [
             'name' => 'required|string|max:191',
             'id_quarter' => 'required|exists:quarters,id',
             'id_user' => 'required|exists:users,id',
             'latitude' => 'required|numeric|between:-90,90',
             'longitude' => 'required|numeric|between:-180,180',
             'type' => 'nullable|string|max:32',
         ], self::MESSAGES_VALIDATION);

         if ($validation->fails()) {
             return response()->json([
                 "response" => 400,
                 "message" => $validation->errors()->first(),
             ]);
         }

         $nom = trim($request->input('name'));
         $idQuartier = $request->input('id_quarter');

         $existant = Location::where('id_quarter', $idQuartier)
             ->whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])
             ->first();

         if ($existant) {
             return response()->json([
                 "response" => 409,
                 "message" => "Le lieu \"{$existant->name}\" existe déjà dans ce quartier",
                 "data" => $existant,
             ]);
         }

         $lieu = Location::create([
             'id_quarter' => $idQuartier,
             'name' => $nom,
             'type' => $request->input('type'),
             // Voir createQuarter : enum sans défaut, aligné sur l'existant.
             'status' => 'pending',
             'id_user' => $request->input('id_user'),
             'latitude' => (string) $request->input('latitude'),
             'longitude' => (string) $request->input('longitude'),
         ]);

         return response()->json([
             "response" => 200,
             "message" => "Lieu enregistré avec succès",
             "data" => $lieu->load('quarter:id,name'),
         ]);
     }

     /**
      * Historique des saisies d'un coursier : ce qu'il a lui-même enregistré.
      *
      * Sert à l'agent à vérifier son propre travail sur le terrain — retrouver
      * un lieu qu'il vient d'ajouter, constater qu'un enregistrement est passé,
      * ou repérer une saisie désactivée depuis le tableau de bord.
      */
     public function getPlacesHistory(Request $request)
     {
         $validation = Validator::make($request->all(), [
             'id_user' => 'required|exists:users,id',
         ], self::MESSAGES_VALIDATION);

         if ($validation->fails()) {
             return response()->json([
                 "response" => 400,
                 "message" => $validation->errors()->first(),
             ]);
         }

         $idUser = $request->input('id_user');

         // select() avant withCount() : l'inverse écrase la sous-requête de
         // comptage et locations_count disparaît de la réponse.
         $quartiers = Quarter::where('id_user', $idUser)
             ->select('id', 'name', 'status', 'created_at')
             ->withCount('locations')
             ->orderByDesc('id')
             ->get();

         $lieux = Location::where('id_user', $idUser)
             ->with('quarter:id,name')
             ->select('id', 'id_quarter', 'name', 'type', 'status', 'latitude', 'longitude', 'created_at')
             ->orderByDesc('id')
             ->get();

         return response()->json([
             "response" => 200,
             "data" => [
                 "quarters" => $quartiers,
                 "locations" => $lieux,
             ],
             "lenght" => count($quartiers) + count($lieux),
         ]);
     }
}
