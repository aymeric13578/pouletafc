<?php

namespace App\Support;

use App\Models\IdempotencyKey;
use Illuminate\Http\JsonResponse;

/**
 * Empêche un double-tap ou un retry réseau sur un bouton sensible de
 * s'exécuter deux fois — voir CLAUDE.md, l'app agent/employé n'a aucun
 * verrou côté serveur sur takeClandoCommand/takeOrderCommand (contrairement
 * à terminatedCourse/terminatedCourseOrder, déjà protégées par
 * DB::transaction + lockForUpdate).
 *
 * Le client génère une clé (UUID) une seule fois par tentative logique et la
 * réutilise s'il retente le même geste après un timeout ou un double-tap. Un
 * second appel avec la même clé reçoit la réponse déjà produite au lieu de
 * relancer l'opération.
 */
class Idempotence
{
    /**
     * @param  string|null  $cle  Fournie par le client ; absente = comportement
     *                            normal, sans protection (rétrocompatible avec
     *                            les appelants qui ne l'envoient pas encore).
     * @param  callable(): JsonResponse  $operation
     */
    public static function executer(?string $cle, string $endpoint, callable $operation): JsonResponse
    {
        $cle = trim((string) $cle);

        if ($cle === '') {
            return $operation();
        }

        $existante = IdempotencyKey::where('key', $cle)->first();
        if ($existante) {
            // Toujours répondu en HTTP 200 : le code "réel" vit dans le champ
            // JSON 'response', pas dans le statut HTTP — même convention que
            // partout ailleurs dans cette API (response()->json() n'est
            // jamais appelé ici avec un second argument de statut).
            return response()->json(json_decode($existante->response_body, true));
        }

        $reponse = $operation();

        /*
        | Seule une réponse de succès est mémorisée. Une erreur métier
        | (solde insuffisant, commande déjà prise par un collègue...) n'a
        | rien exécuté : la même clé doit pouvoir être retentée une fois la
        | cause corrigée, plutôt que de rejouer indéfiniment le même échec.
        | Le statut HTTP réel valant toujours 200 dans cette API (voir
        | ci-dessus), c'est le champ JSON 'response' qui porte le vrai
        | résultat de l'opération.
        */
        $corps = json_decode($reponse->getContent(), true);
        if (is_array($corps) && (int) ($corps['response'] ?? 0) === 200) {
            IdempotencyKey::create([
                'key' => $cle,
                'endpoint' => $endpoint,
                'response_status' => $reponse->getStatusCode(),
                'response_body' => $reponse->getContent(),
            ]);
        }

        return $reponse;
    }
}
