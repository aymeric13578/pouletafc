<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Résout le jeton Sanctum envoyé par les applications mobiles vers
 * l'utilisateur réel qui l'a émis — jamais l'en-tête Authorization, voir
 * spec 2026-09-01. Généralise MaBoutiqueController::boutiqueVerifiee(), qui
 * dupliquait cette même logique pour un seul contrôleur.
 *
 * Le nom du champ de requête est paramétrable (`$champ`, par défaut
 * `token`) car certains endpoints — deverrouillerEcranKiosk notamment —
 * utilisent déjà ce même nom pour un jeton d'un autre type (jeton de
 * déverrouillage de kiosk, sans rapport avec la session utilisateur) : ne
 * jamais appeler cette méthode sans vérifier que `$champ` désigne bien un
 * jeton Sanctum de session sur cet endpoint précis.
 */
class ApiAuthentification
{
    public function utilisateur(Request $request, string $champ = 'token'): ?User
    {
        $brut = $request->input($champ);

        if (! is_string($brut)) {
            return null;
        }

        $jeton = PersonalAccessToken::findToken($brut);

        if (! $jeton || ! $jeton->tokenable instanceof User) {
            return null;
        }

        return $jeton->tokenable;
    }

    public function utilisateurOuErreur(Request $request, string $champ = 'token'): User|JsonResponse
    {
        $utilisateur = $this->utilisateur($request, $champ);

        if (! $utilisateur) {
            return response()->json([
                'response' => 401,
                'message' => 'Session expirée, reconnectez-vous',
            ]);
        }

        return $utilisateur;
    }
}
