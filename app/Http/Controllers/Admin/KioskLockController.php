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
            KioskLock::poserCookie($jeton->page);

            return response()->json(['unlocked' => true]);
        }

        if ($jeton->expires_at->isPast()) {
            return response()->json(['unlocked' => false, 'expired' => true]);
        }

        return response()->json(['unlocked' => false]);
    }
}
