<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\KioskUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Déverrouillage d'un écran "mur" depuis l'appli employé (scan du QR affiché
 * à l'écran) — voir App\Support\KioskLock. Même convention que le reste de
 * l'API v1.0 (règle 8, CLAUDE.md) : id_user n'est pas vérifié comme étant
 * réellement un compte employee_afc/admin, seulement mémorisé pour l'audit.
 */
class KioskLockController extends Controller
{
    public function deverrouiller(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'token' => ['required', 'string'],
            'id_user' => ['required', 'integer'],
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
            'unlocked_by_user_id' => $valide['id_user'],
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
