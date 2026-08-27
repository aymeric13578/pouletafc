<?php

namespace App\Support;

use App\Models\KioskUnlockToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Verrou QR pour les écrans "mur" sans authentification (/commandes, /clando,
 * /commandes/carte — voir routes/shop.php et le commentaire qui documente le
 * risque). Un écran affiche un QR à la place du contenu tant qu'aucun employé
 * ne l'a scanné depuis l'appli ; une fois débloqué, un cookie signé Laravel
 * (EncryptCookies, infalsifiable sans APP_KEY) fait passer le contenu jusqu'à
 * minuit. Voir docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 */
class KioskLock
{
    private const DUREE_JETON_MINUTES = 10;

    public static function nomCookie(string $page): string
    {
        return 'kiosk_unlock_' . $page;
    }

    public static function estDeverrouille(Request $request, string $page): bool
    {
        return $request->cookie(self::nomCookie($page)) === '1';
    }

    /**
     * Jeton affichable pour cette page : réutilise le dernier jeton encore
     * valide (pas expiré, pas déjà scanné) s'il existe, en crée un sinon —
     * évite de régénérer un nouveau QR à chaque sondage du navigateur.
     */
    public static function jetonActif(string $page): KioskUnlockToken
    {
        $jeton = KioskUnlockToken::where('page', $page)
            ->where('session_id', session()->getId())
            ->whereNull('unlocked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($jeton) {
            return $jeton;
        }

        return KioskUnlockToken::create([
            'page' => $page,
            'token' => Str::random(48),
            'session_id' => session()->getId(),
            'expires_at' => now()->addMinutes(self::DUREE_JETON_MINUTES),
        ]);
    }

    public static function poserCookie(string $page): void
    {
        // now() est en UTC (config('app.timezone')) — "minuit" doit être celui
        // de Douala, pas celui d'UTC, sinon le cookie expire au milieu de la
        // journée locale (ou la couvre en trop) selon l'heure de l'année.
        $maintenant = now('Africa/Douala');
        $minutesJusquaMinuit = max(1, (int) $maintenant->diffInMinutes($maintenant->copy()->endOfDay()->addSecond()));

        Cookie::queue(self::nomCookie($page), '1', $minutesJusquaMinuit);
    }
}
