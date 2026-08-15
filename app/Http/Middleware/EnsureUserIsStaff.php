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

        /*
         | Le droit s'applique ici, pas seulement dans la barre de navigation.
         |
         | Masquer un lien ne protège rien : l'URL d'un écran du tableau de bord
         | se devine — /dashboard/configuration se lit sur n'importe quelle page.
         | Un employé chargé des commandes pouvait ainsi ouvrir la grille des
         | commissions sans qu'aucun menu ne l'y invite.
         |
         | Les administrateurs passent toujours, et les routes hors menu aussi :
         | actions POST, flux de rafraîchissement et sous-pages n'ont pas de
         | droit propre et appartiennent à l'écran qui les appelle.
         */
        abort_unless(
            \App\Support\MenuTableauDeBord::autorise($user, $request->route()?->getName()),
            403,
            "Vous n'avez pas accès à cette partie du tableau de bord."
        );

        return $next($request);
    }
}
