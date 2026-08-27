<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskUnlockToken;
use App\Support\KioskLock;
use Illuminate\Http\JsonResponse;

/**
 * Sondé par les 3 écrans "mur" pendant qu'ils affichent un QR — voir
 * App\Support\KioskLock. Volontairement sans authentification : c'est
 * justement l'écran non authentifié qui interroge ce point pour savoir s'il
 * peut se débloquer.
 */
class KioskLockController extends Controller
{
    public function statut(string $token): JsonResponse
    {
        $jeton = KioskUnlockToken::where('token', $token)->first();

        if (! $jeton) {
            return response()->json(['unlocked' => false, 'expired' => true]);
        }

        if ($jeton->unlocked_at) {
            // Un jeton déverrouillé mais expiré (vieux QR rejoué des semaines
            // plus tard) ne doit plus poser de cookie — voir le commentaire sur
            // l'allongement de expires_at dans API\KioskLockController::deverrouiller().
            if ($jeton->expires_at->isPast()) {
                return response()->json(['unlocked' => false, 'expired' => true]);
            }

            // Le jeton n'a été émis que pour la session qui l'a demandé (voir
            // KioskLock::jetonActif) : un tiers qui aurait intercepté le token
            // dans les logs d'accès ne doit pas pouvoir se faire poser le cookie
            // même si le token a bien été déverrouillé par le vrai écran.
            if ($jeton->session_id !== session()->getId()) {
                return response()->json(['unlocked' => false]);
            }

            KioskLock::poserCookie($jeton->page);

            return response()->json(['unlocked' => true]);
        }

        if ($jeton->expires_at->isPast()) {
            return response()->json(['unlocked' => false, 'expired' => true]);
        }

        return response()->json(['unlocked' => false]);
    }
}
