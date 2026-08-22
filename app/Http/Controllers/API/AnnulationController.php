<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\AnnulationDeCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ce qu'une application a besoin de savoir d'une annulation.
 *
 * Trois besoins distincts, mais une même notion : une commande annulée n'est
 * plus là. L'application agent doit pouvoir le constater sans attendre — sa
 * fenêtre reste ouverte tant que l'agent n'y touche pas (disponibilite) ;
 * l'application cliente doit savoir, avant même d'afficher un bouton
 * « Annuler », ce qu'elle a le droit de proposer (eligibilite) ; et les deux
 * doivent pouvoir annuler elles-mêmes en disant pourquoi (annuler).
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

        /*
        | Un client n'annule pas une commande déjà livrée.
        |
        | Le contrôle est ici et non dans l'application : celle-ci décide quoi
        | afficher, elle ne décide pas ce qui est permis. Une version installée
        | il y a trois mois continue d'appeler cette route avec ses propres
        | idées de ce qui est annulable.
        */
        if ($auteur === 'client') {
            $motifDeBlocage = AnnulationDeCommande::motifDeBlocageClient($ligne, (string) $request->input('type'));

            if ($motifDeBlocage !== null) {
                return response()->json([
                    'response' => 409,
                    'message' => "Cette commande ne peut plus être annulée.",
                    'cancel_block_reason' => $motifDeBlocage,
                ]);
            }
        }

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
     *
     * « for=client » sert la liste écrite à la première personne pour l'app
     * cliente ; par défaut, celle du comptoir/de l'agent qui annule pour
     * quelqu'un d'autre (comportement inchangé pour les appelants existants).
     */
    public function motifs(Request $request): JsonResponse
    {
        return response()->json([
            'response' => 200,
            'data' => $request->input('for') === 'client'
                ? AnnulationDeCommande::MOTIFS_CLIENT
                : AnnulationDeCommande::MOTIFS_COURANTS,
        ]);
    }

    /**
     * Ce que l'app cliente doit afficher avant même de proposer d'annuler.
     *
     * Ne jamais laisser le téléphone décider seul avec un minuteur : la
     * réponse reflète l'état réel côté serveur au moment de l'appel, pas une
     * estimation locale qui peut avoir dérivé.
     */
    public function eligibilite(Request $request): JsonResponse
    {
        $type = (string) $request->input('type');
        $ligne = AnnulationDeCommande::retrouver($type, $request->input('id'));

        $motifDeBlocage = AnnulationDeCommande::motifDeBlocageClient($ligne, $type);

        if ($motifDeBlocage !== null) {
            return response()->json([
                'response' => 200,
                'can_cancel' => false,
                'cancel_block_reason' => $motifDeBlocage,
                // Aucun frais d'annulation n'existe dans ce système aujourd'hui :
                // le champ est là pour que l'app n'ait pas à distinguer « pas de
                // frais » de « le champ n'existe pas », le jour où ça change.
                'cancellation_fee' => 0,
            ]);
        }

        return response()->json([
            'response' => 200,
            'can_cancel' => true,
            'cancellation_reason_required' => true,
            'cancellation_fee' => 0,
        ]);
    }
}
