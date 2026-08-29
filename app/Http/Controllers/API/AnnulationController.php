<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use App\Models\order_detail;
use App\Support\AnnulationDeCommande;
use App\Support\ColonnesDisponibles;
use Illuminate\Database\Eloquent\Builder;
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
     * Annulation la plus récente touchant une course/commande de cet agent.
     *
     * VeilleDisponibilite/le minuteur de commandOrder.dart ne détectent une
     * annulation que pendant que l'écran concerné est ouvert — un agent qui
     * a déjà quitté l'écran de suivi (retour à l'accueil, à l'historique...)
     * ne sait plus qu'une course qu'il pensait toujours en cours a été
     * annulée entre-temps, par exemple par la boutique
     * (MaBoutiqueController::cancelMyShopDeliveryRequest). Le service de
     * fond (order_background_service.dart, sondage 5s, indépendant de
     * l'écran affiché) interroge cette route pour le signaler malgré tout.
     *
     * Fenêtre de 10 minutes : au-delà, l'agent l'aura de toute façon vue en
     * rouvrant l'écran ou l'app — pas besoin de la resignaler indéfiniment.
     */
    public function annulationRecente(Request $request): JsonResponse
    {
        $idAgent = $request->input('id_agent');

        if (! $idAgent) {
            return response()->json(['response' => 400, 'data' => null]);
        }

        $depuis = now()->subMinutes(10);

        $course = self::dernierAnnuleePour(Clando::query(), $idAgent, $depuis);
        $commande = self::dernierAnnuleePour(order_detail::query(), $idAgent, $depuis);

        $champDate = fn ($ligne) => ColonnesDisponibles::existe($ligne->getTable(), 'cancelled_at')
            ? $ligne->cancelled_at
            : $ligne->updated_at;

        $ligne = collect([$course, $commande])
            ->filter()
            ->sortByDesc($champDate)
            ->first();

        if (! $ligne) {
            return response()->json(['response' => 200, 'data' => null]);
        }

        return response()->json([
            'response' => 200,
            'data' => [
                'type' => $ligne instanceof Clando ? 'clando' : 'order',
                'id' => $ligne->id,
                'ref' => $ligne->ref,
                'cancel_reason' => $ligne->cancel_reason ?? null,
                'cancelled_by' => $ligne->cancelled_by ?? null,
                'cancelled_at' => $champDate($ligne),
            ],
        ]);
    }

    /**
     * Dernière ligne annulée pour cet agent — sans jamais filtrer sur une
     * colonne absente de la table.
     *
     * La migration qui ajoute cancel_reason/cancelled_at/cancelled_by
     * (2026_08_17_000002) est défensive côté schéma (elle ne crée que ce qui
     * manque), mais rien ne garantit qu'elle ait été exécutée sur toutes les
     * tables de production ("créées à la main" — voir son propre
     * commentaire) : filtrer directement sur ces colonnes ferait échouer la
     * requête elle-même (erreur SQL, pas juste zéro résultat) là où elles
     * n'existent pas encore. Repli sur updated_at, sans motif/auteur, plutôt
     * que de ne rien détecter du tout.
     */
    private static function dernierAnnuleePour(Builder $requete, $idAgent, \DateTimeInterface $depuis)
    {
        $table = $requete->getModel()->getTable();
        $requete->where('id_agent', $idAgent)->where('status', AnnulationDeCommande::STATUT);

        if (ColonnesDisponibles::existe($table, 'cancelled_at')) {
            $requete->where('cancelled_at', '>=', $depuis)->latest('cancelled_at');
        } else {
            $requete->where('updated_at', '>=', $depuis)->latest('updated_at');
        }

        return $requete->first();
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
