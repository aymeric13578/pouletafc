<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use App\Models\Commentaire;
use App\Models\order_detail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commentaires laissés par les clients sur une prestation.
 *
 * Une note se donne une fois et se referme ; un commentaire peut venir après
 * coup, se répéter, ou dire quelque chose qu'aucune émoticône ne traduit.
 */
class CommentaireController extends Controller
{
    /** Longueur d'un commentaire : assez pour raconter, pas pour déposer un roman. */
    private const LONGUEUR_MAX = 2000;

    public function postCommentaire(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_user' => ['required', 'integer'],
            'id_agent' => ['nullable', 'integer'],
            'id_order' => ['nullable', 'integer'],
            'id_clando' => ['nullable', 'integer'],
            'contenu' => ['required', 'string', 'min:2', 'max:' . self::LONGUEUR_MAX],
        ]);

        if (! $request->filled('id_order') && ! $request->filled('id_clando')) {
            return response()->json([
                'response' => 422,
                'message' => 'Précisez la prestation commentée.',
            ]);
        }

        /*
         | L'agent est déduit de la prestation plutôt que cru sur parole.
         |
         | Le laisser au client permettrait d'attribuer un commentaire — et donc
         | un reproche — à n'importe quel agent.
         */
        $idAgent = $request->filled('id_clando')
            ? Clando::whereKey($request->input('id_clando'))->value('id_agent')
            : order_detail::whereKey($request->input('id_order'))->value('id_agent');

        $commentaire = Commentaire::create([
            'id_user' => $valide['id_user'],
            'id_agent' => $idAgent,
            'id_order' => $request->input('id_order'),
            'id_clando' => $request->input('id_clando'),
            'contenu' => trim($valide['contenu']),
        ]);

        return response()->json([
            'response' => 200,
            'message' => 'Merci, votre commentaire est enregistré.',
            'data' => ['id' => $commentaire->id],
        ]);
    }

    /**
     * Commentaires d'une prestation, du plus récent au plus ancien.
     */
    public function getCommentairesPrestation(Request $request): JsonResponse
    {
        $idOrder = $request->input('id_order');
        $idClando = $request->input('id_clando');

        if (! $idOrder && ! $idClando) {
            return response()->json([
                'response' => 400,
                'message' => 'Précisez la prestation.',
                'data' => [],
            ]);
        }

        $commentaires = Commentaire::visibles()
            ->with('client:id,name')
            ->when($idOrder, fn ($q) => $q->where('id_order', $idOrder))
            ->when($idClando, fn ($q) => $q->where('id_clando', $idClando))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'response' => 200,
            'data' => $commentaires->map(fn (Commentaire $c) => $this->transformer($c))->values(),
        ]);
    }

    /**
     * Commentaires reçus par un agent.
     *
     * L'agent doit pouvoir lire ce qu'on dit de lui, comme il lit ses notes :
     * une moyenne ne dit pas quoi corriger.
     */
    public function getCommentairesAgent(Request $request): JsonResponse
    {
        $idAgent = (int) $request->input('id_agent');

        if (! $idAgent) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant agent manquant',
                'data' => [],
            ]);
        }

        $commentaires = Commentaire::visibles()
            ->with('client:id,name')
            ->where('id_agent', $idAgent)
            ->orderByDesc('id')
            ->limit((int) $request->input('limite', 100))
            ->get();

        return response()->json([
            'response' => 200,
            'data' => $commentaires->map(fn (Commentaire $c) => $this->transformer($c))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformer(Commentaire $c): array
    {
        return [
            'id' => $c->id,
            'contenu' => $c->contenu,
            'client' => $c->client?->name,
            'prestation' => $c->id_clando ? 'clando' : 'commande',
            'id_order' => $c->id_order,
            'id_clando' => $c->id_clando,
            'reponse' => $c->reponse,
            'repondu_le' => $c->repondu_le?->toIso8601String(),
            'date' => $c->created_at?->toIso8601String(),
        ];
    }
}
