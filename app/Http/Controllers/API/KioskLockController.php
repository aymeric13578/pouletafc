<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\KioskUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Déverrouillage d'un écran "mur" depuis l'appli employé (scan du QR affiché
 * à l'écran) — voir App\Support\KioskLock. Deux jetons distincts et
 * obligatoires : `token` (jeton de déverrouillage kiosk, prouve qu'un QR
 * affiché à l'écran a été scanné) et `session_token` (jeton Sanctum,
 * prouve que le scanneur est un compte employee_afc/admin authentifié —
 * voir App\Support\ApiAuthentification, qui utilise délibérément un nom de
 * champ différent de `token` pour éviter toute collision entre les deux).
 */
class KioskLockController extends Controller
{
    public function deverrouiller(Request $request): JsonResponse
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request, 'session_token');
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        if (! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Seuls un employé ou un administrateur peuvent déverrouiller un écran."]);
        }

        $valide = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $jeton = KioskUnlockToken::where('token', $valide['token'])->first();

        if (! $jeton || $jeton->expires_at->isPast()) {
            return response()->json([
                'response' => 404,
                'message' => "Ce QR code n'est plus valide, ouvre l'écran pour en afficher un nouveau.",
            ]);
        }

        if ($jeton->unlocked_at) {
            return response()->json([
                'response' => 409,
                'message' => 'Cet écran a déjà été déverrouillé.',
            ]);
        }

        $jeton->update([
            'unlocked_at' => now(),
            'unlocked_by_user_id' => $utilisateur->id,
            // Le sondage côté écran (statut()) refuse de poser le cookie une
            // fois expires_at dépassé (voir Admin\KioskLockController::statut) —
            // sans cet allongement, la fenêtre de 10 minutes utilisée pour
            // afficher le QR expirerait presque aussitôt après le scan et
            // l'écran resterait bloqué malgré un déverrouillage réussi. 18h
            // couvre le reste d'une journée de travail sans faire de ce jeton
            // un identifiant permanent.
            'expires_at' => now()->addHours(18),
        ]);

        return response()->json([
            'response' => 200,
            'message' => 'Écran déverrouillé.',
        ]);
    }
}
