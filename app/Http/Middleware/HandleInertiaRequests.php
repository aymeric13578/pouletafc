<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Le back-office admin (routes préfixées "admin") continue d'utiliser le
     * thème existant ("app"), tout le reste du site (boutique, panier,
     * checkout, espace client, auth...) utilise le nouveau layout Tailwind
     * allégé ("shop"), sans les assets du template d'admin.
     */
    public function rootView(Request $request): string
    {
        return $request->is('admin*') ? 'app' : 'shop';
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cart = app(CartService::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                /*
                 | Boutique rattachée au compte, s'il y en a une.
                 |
                 | Sans cette information, un marchand n'avait aucun moyen de
                 | retrouver son espace : il y était envoyé une fois à la connexion,
                 | et dès qu'il revenait sur la boutique publique, plus aucun lien
                 | ne l'y ramenait. Le rôle ne suffit pas à en décider : des
                 | boutiques sont rattachées à des comptes "agent" ou
                 | "employee_afc", c'est le rattachement qui fait foi.
                 */
                'shop' => $request->user()
                    ? Shop::where('id_user', $request->user()->id)->value('shop_name')
                    : null,
            ],
            'cart' => [
                'count' => $cart->count(),
                'subtotal' => $cart->subtotal(),
                'items' => $cart->items(),
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
