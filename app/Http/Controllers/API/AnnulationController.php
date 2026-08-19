<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\AnnulationDeCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce qu'une application a besoin de savoir d'une annulation.
 *
 * Deux besoins distincts, mais une même notion : une commande annulée n'est plus
 * là. L'application agent doit pouvoir le constater sans attendre — sa fenêtre
 * reste ouverte tant que l'agent n'y touche pas — et pouvoir elle-même annuler
 * en disant pourquoi.
 */
class AnnulationController extends Controller
{
    /**
     * La commande ou la course est-elle encore prenable ?
     *
     * Interrogé en boucle par la fenêtre ouverte sur le téléphone de l'agent :
     * la réponse tient en quelques champs, et se veut assez légère pour être
     * demandée toutes les trois secondes sans peser sur le forfait.
     */
    public function disponibilite(Request $request): JsonResponse
    {
        $ligne = AnnulationDeCommande::retrouver(
            (string) $request->input('type'),
            $request->input('id')
        );

        if (! $ligne) {
            return response()->json([
                'response' => 200,
                'disponible' => false,
                'annulee' => false,
                'statut' => null,
                'message' => 'Commande introuvable.',
            ]);
        }

        $annulee = AnnulationDeCommande::estAnnulee($ligne);
        $motif = $annulee ? ($ligne->cancel_reason ?? null) : null;

        return response()->json([
            'response' => 200,
            // « Prenable » et « annulée » ne se confondent pas : une commande
            // déjà prise par un collègue n'est pas annulée, et le message que
            // lit l'agent doit le dire.
            'disponible' => AnnulationDeCommande::encorePrenable($ligne),
            'annulee' => $annulee,
            'statut' => $ligne->status,
            'motif' => $motif,
            'message' => $annulee
                ? ($motif ? 'Commande annulée : ' . $motif : 'Cette commande a été annulée.')
                : null,
        ]);
    }

    /**
     * Annule une commande ou une course, motif obligatoire.
     *
     * L'auteur est déclaré par l'appelant — l'application agent dit « agent »,
     * l'application cliente dit « client ». Sans cette distinction, un même
     * motif ne raconte pas la même histoire selon qui l'a écrit, et l'écart
     * entre les deux est précisément ce qu'on veut pouvoir lire.
     */
    public function annuler(Request $request): JsonResponse
    {
        $motif = (string) $request->input('reason', $request->input('motif'));

        if (! AnnulationDeCommande::motifValide($motif)) {
            return response()->json([
                'response' => 400,
                'message' => "Indiquez pourquoi c'est annulé.",
            ]);
        }

        $ligne = AnnulationDeCommande::retrouver(
            (string) $request->input('type'),
            $request->input('id')
        );

        if (! $ligne) {
            return response()->json(['response' => 404, 'message' => 'Commande introuvable.']);
        }

        if (AnnulationDeCommande::estAnnulee($ligne)) {
            return response()->json([
                'response' => 200,
                'message' => 'Cette commande était déjà annulée.',
                'motif' => $ligne->cancel_reason ?? null,
            ]);
        }

        $auteur = in_array($request->input('by'), ['client', 'agent', 'admin'], true)
            ? $request->input('by')
            : 'agent';

        if (! AnnulationDeCommande::appliquer($ligne, $motif, $auteur)) {
            return response()->json(['response' => 400, 'message' => "L'annulation n'a pas pu être enregistrée."]);
        }

        return response()->json([
            'response' => 200,
            'message' => 'Annulation enregistrée.',
            'motif' => AnnulationDeCommande::nettoyerLeMotif($motif),
        ]);
    }

    /**
     * Motifs proposés d'un geste, pour éviter la saisie au clavier sur le terrain.
     *
     * Servis par l'API plutôt que figés dans les applications : la liste évolue
     * avec l'activité, et une application ne se met pas à jour d'un claquement
     * de doigts sur les téléphones déjà installés.
     */
    public function motifs(): JsonResponse
    {
        return response()->json([
            'response' => 200,
            'data' => AnnulationDeCommande::MOTIFS_COURANTS,
        ]);
    }
}
