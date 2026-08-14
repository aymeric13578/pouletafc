<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Parameter;
use App\Support\NotationAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appréciations laissées par les clients après une prestation.
 *
 * Une prestation est une commande, une course clando ou une course de coursier.
 * Les deux dernières se distinguent par la table où elles vivent, pas par la
 * façon de les noter : le client note toujours l'agent qui l'a servi.
 */
class NoteController extends Controller
{
    public function makeNote(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_user' => 'required|integer',
            'id_agent' => 'required|integer',
            'id_order' => 'nullable|integer',
            'id_clando' => 'nullable|integer',
            'comment' => 'nullable|string|max:2000',
            /*
             | « verybad » manquait ici alors que le calcul le comptait déjà :
             | la pire appréciation était proposée nulle part et refusée
             | partout. Un client mécontent n'avait pas de case à cocher.
             */
            'note' => 'required|in:' . implode(',', Parameter::APPRECIATIONS),
        ]);

        if (! $request->filled('id_order') && ! $request->filled('id_clando')) {
            return response()->json([
                'response' => 422,
                'message' => 'Précisez la prestation notée.',
            ]);
        }

        if ($this->dejaNotee($request)) {
            return response()->json([
                'response' => 404,
                'message' => 'Cette prestation a déjà été notée.',
            ]);
        }

        $note = Note::create([
            'id_user' => $valide['id_user'],
            'id_agent' => $valide['id_agent'],
            'id_order' => $request->input('id_order'),
            'id_clando' => $request->input('id_clando'),
            'comment' => $request->input('comment'),
            'note' => $valide['note'],
        ]);

        return response()->json([
            'response' => 200,
            'message' => 'Merci, votre appréciation est enregistrée.',
            // Le score mis à jour repart avec la réponse : l'application affiche
            // la nouvelle moyenne sans un second aller-retour.
            'data' => NotationAgent::pourAgent((int) $valide['id_agent']) + ['id' => $note->id],
        ]);
    }

    private function dejaNotee(Request $request): bool
    {
        $requete = Note::where('id_user', $request->input('id_user'));

        if ($request->filled('id_order')) {
            return (clone $requete)->where('id_order', $request->input('id_order'))->exists();
        }

        return (clone $requete)->where('id_clando', $request->input('id_clando'))->exists();
    }

    /**
     * Bilan chiffré d'un agent.
     *
     * Renvoyait 404 quand le total tombait à zéro — ce qui arrive à un agent
     * dont les bonnes et les mauvaises notes s'annulent, exactement comme à
     * celui qui n'en a aucune. Deux situations opposées, une seule réponse :
     * l'application affichait « aucune note » à un agent qui en avait vingt.
     */
    public function getAgentNote(Request $request): JsonResponse
    {
        $idAgent = (int) $request->input('id_agent');

        if (! $idAgent) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant agent manquant',
                'data' => null,
            ]);
        }

        $bilan = NotationAgent::pourAgent($idAgent);

        return response()->json([
            'response' => 200,
            // Les anciennes applications lisent data.bad, data.good, data.total
            // à plat : on garde ces clés pour ne pas les casser au déploiement.
            'data' => $bilan['compte'] + [
                'total' => $bilan['total'],
                'moyenne' => $bilan['moyenne'],
                'sur_cinq' => $bilan['sur_cinq'],
                'nombre' => $bilan['nombre'],
            ],
        ]);
    }

    /**
     * Les appréciations une à une, avec commentaire et prestation.
     *
     * C'est ce que l'agent voit dans son application : non seulement sa moyenne,
     * mais ce qu'on lui a reproché ou reconnu, course par course. Une moyenne
     * seule ne dit pas quoi corriger.
     */
    public function getNotesAgent(Request $request): JsonResponse
    {
        $idAgent = (int) $request->input('id_agent');

        if (! $idAgent) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant agent manquant',
                'data' => null,
            ]);
        }

        $limite = (int) $request->input('limite', 100);

        return response()->json([
            'response' => 200,
            'data' => [
                'bilan' => NotationAgent::pourAgent($idAgent),
                'appreciations' => NotationAgent::appreciations($idAgent, $limite > 0 ? $limite : null),
            ],
        ]);
    }

    /**
     * Le barème en vigueur, pour que les applications affichent les mêmes
     * émoticônes et les mêmes points que le tableau de bord.
     */
    public function getBaremeNotation(): JsonResponse
    {
        $bareme = NotationAgent::bareme();

        return response()->json([
            'response' => 200,
            'data' => collect(Parameter::APPRECIATIONS)->map(fn (string $cle) => [
                'cle' => $cle,
                'libelle' => NotationAgent::LIBELLES[$cle],
                'emoji' => NotationAgent::EMOJIS[$cle],
                'points' => $bareme[$cle],
            ])->values(),
        ]);
    }

    public function verifiedStatusOrderNote(Request $request): JsonResponse
    {
        return $this->dejaNoteePour($request, 'id_order');
    }

    public function verifiedStatusClandoNote(Request $request): JsonResponse
    {
        return $this->dejaNoteePour($request, 'id_clando');
    }

    private function dejaNoteePour(Request $request, string $colonne): JsonResponse
    {
        $identifiant = $request->input($colonne);

        $existe = $identifiant
            && Note::where($colonne, $identifiant)
                ->where('id_user', $request->input('id_user'))
                ->exists();

        return response()->json([
            'response' => 200,
            'data' => $existe ? 1 : 0,
        ]);
    }
}
