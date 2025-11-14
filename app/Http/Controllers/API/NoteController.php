<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function makeNote(Request $request)
    {
        // Valider les données entrantes
        $request->validate([
            'id_user' => 'required',
            'id_agent' => 'required',
            'id_order' => 'nullable',
            'id_clando' => 'nullable',
            'comment' => 'nullable|string',
            'note' => 'required|in:bad,average,good,excellent',
        ]);
         $verifiedOrderNote = null;
         $verifiedClandoNote = null;
        // Vérifier si une note existe déjà pour id_order ou id_clando
        if($request->id_order != null) $verifiedOrderNote = Note::where('id_order', $request->id_order)->where('id_user', $request->id_user)->first();
        if($request->id_clando != null)  $verifiedClandoNote = Note::where('id_clando', $request->id_clando)->where('id_user', $request->id_user)->first();

        if ($verifiedOrderNote != null || $verifiedClandoNote !=null ) {
            return response()->json([
                'response' => 404,
                'message' => 'Impossible de noter a nouveau'
            ], 404, [], JSON_THROW_ON_ERROR);
        }

        // Créer une nouvelle note
        $note = Note::create([
            'id_user' => $request->id_user,
            'id_agent' => $request->id_agent,
            'id_order' => $request->id_order,
            'id_clando' => $request->id_clando,
            'comment' => $request->comment,
            'note' => $request->note,
        ]);

        if ($note) {
            return response()->json([
                'response' => 200,
                'message' => 'Note enregistree avec succes'
            ], 200, [], JSON_THROW_ON_ERROR);
        } else {
            return response()->json([
                'response' => 500,
                'message' => 'Erreur lors de l\'enregistrement de la note'
            ], 500, [], JSON_THROW_ON_ERROR);
        }
    }

    public function getAgentNote(Request $request)
    {
        // Valider les données entrantes
      
        $idAgent = $request->input('id_agent');

        // Compter les notes pour chaque catégorie
        $noteCounts = Note::select('note')
            ->selectRaw('COUNT(*) as count')
            ->where('id_agent', $idAgent)
            ->groupBy('note')
            ->pluck('count', 'note')
            ->toArray();

        // Initialiser les compteurs pour chaque type de note
        $result = [
              'verybad' => 0,
            'bad' => 0,
            'average' => 0,
            'good' => 0,
            'excellent' => 0,
            'total' => 0,
        ];

        // Remplir les compteurs avec les données récupérées
        foreach ($noteCounts as $noteType => $count) {
            if (in_array($noteType, array_keys($result))) {
                $result[$noteType] = $count;
            }
        }

        // Calculer le total des notes
        $result['total'] = array_sum([
            -2*$result['verybad'],
            -1*$result['bad'],
            $result['average'],
            1.5*$result['good'],
            2*$result['excellent'],
        ]);

        // Vérifier si l'agent a des notes
        if ($result['total'] === 0) {
            return response()->json([
                'response' => 404,
               
                'data' => $result
            ], 404, [], JSON_THROW_ON_ERROR);
        }

        // Retourner les résultats
        return response()->json([
            'response' => 200,
      
            'data' => $result
        ], 200, [], JSON_THROW_ON_ERROR);
    }
    
    
  public function   verifiedStatusOrderNote(Request $request)
    {
         $verifiedOrderNote = null;
      
        // Vérifier si une note existe déjà pour id_order ou id_clando
        if($request->id_order != null) $verifiedOrderNote = Note::where('id_order', $request->id_order)->where('id_user', $request->id_user)->first();
        if ($verifiedOrderNote !=null ) {
            return response()->json([
                'response' => 200,
                'data' => 1
            ]);
        }
        
        
        return response()->json([
                'response' => 200,
                'data' => 0
            ]);
        
        
        
        
    }
    
      public function   verifiedStatusClandoNote(Request $request)
    {
         $verifiedClandoNote = null;
      
        // Vérifier si une note existe déjà pour id_order ou id_clando
        if($request->id_clando != null) $verifiedClandoNote = Note::where('id_clando', $request->id_clando)->where('id_user', $request->id_user)->first();
        if ($verifiedClandoNote !=null ) {
            return response()->json([
                'response' => 200,
                'data' => 1
            ]);
        }
        

        
        
        return response()->json([
                'response' => 200,
                'data' => 0
            ]);
        
        
        
        
    }
}