<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint le back-office aux rôles internes.
 *
 * Les pages /dashboard n'étaient protégées que par "auth" : n'importe quel compte
 * connecté — y compris un client de la boutique — pouvait les ouvrir et créer,
 * modifier ou supprimer des produits. Masquer le lien dans l'interface ne change
 * rien à ça, l'URL restant devinable.
 */
class EnsureUserIsStaff
{
    /**
     * Rôles autorisés. Les agents et marchands ont leurs propres espaces
     * (merchand-dashboard) et ne sont volontairement pas inclus ici.
     */
    public const ROLES = ['admin', 'employee_afc'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($user->role, self::ROLES, true), 403, "Cet espace est réservé à l'équipe Poulet AFC.");

        return $next($request);
    }
}
