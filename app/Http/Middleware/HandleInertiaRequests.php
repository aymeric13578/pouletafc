<?php

namespace App\Http\Middleware;

use App\Models\Category;
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
