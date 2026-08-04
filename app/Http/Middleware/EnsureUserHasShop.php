<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'espace marchand aux utilisateurs rattachés à une boutique, et rend
 * cette boutique disponible pour toute la requête.
 *
 * Tout l'espace repose sur ce rattachement : sans lui, on ne saurait pas quels
 * produits ni quelles commandes afficher. Le résoudre une fois ici évite que
 * chaque écran refasse la requête — et surtout évite qu'un écran oublie de
 * filtrer et expose les données d'une autre boutique.
 */
class EnsureUserHasShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $boutique = Shop::where('id_user', $user->id)->first();

        abort_unless(
            $boutique,
            403,
            "Aucune boutique ne vous est rattachée. Contactez l'administrateur de Poulet AFC."
        );

        // Partagée avec les vues : la barre latérale y lit le nom de la boutique.
        $request->attributes->set('boutique', $boutique);
        view()->share('boutiqueCourante', $boutique);

        return $next($request);
    }
}
