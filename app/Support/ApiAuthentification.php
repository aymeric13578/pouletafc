<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Résout le jeton Sanctum envoyé par les applications mobiles (champ `token`,
 * jamais l'en-tête Authorization — voir spec 2026-09-01) vers l'utilisateur
 * réel qui l'a émis. Généralise MaBoutiqueController::boutiqueVerifiee(), qui
 * dupliquait cette même logique pour un seul contrôleur.
 */
class ApiAuthentification
{
    public function utilisateur(Request $request): ?User
    {
        $jeton = PersonalAccessToken::findToken((string) $request->input('token'));

        if (! $jeton || ! $jeton->tokenable instanceof User) {
            return null;
        }

        return $jeton->tokenable;
    }

    public function utilisateurOuErreur(Request $request): User|JsonResponse
    {
        $utilisateur = $this->utilisateur($request);

        if (! $utilisateur) {
            return response()->json([
                'response' => 401,
                'message' => 'Session expirée, reconnectez-vous',
            ]);
        }

        return $utilisateur;
    }
}
