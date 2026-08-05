<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Models\Shop;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Destination après connexion, selon le profil.
     *
     * $redirectTo envoyait tout le monde sur /dashboard : un marchand y était
     * accueilli par « 403 — cet espace est réservé à l'équipe », sans aucun moyen
     * d'atteindre le sien. Un client y récoltait la même erreur.
     *
     * L'ordre compte : l'équipe interne passe avant le rattachement à une
     * boutique, car certains employés en possèdent une et doivent malgré tout
     * arriver sur le tableau de bord.
     *
     * Cette méthode est appelée par le trait AuthenticatesUsers et l'emporte sur
     * la propriété $redirectTo, conservée comme repli.
     */
    /**
     * Écarte l'URL mémorisée avant la connexion quand l'utilisateur n'y a pas droit.
     *
     * AuthenticatesUsers termine par redirect()->intended(), qui privilégie cette
     * URL sur la destination calculée. Un marchand arrivé sur l'écran de connexion
     * depuis /dashboard y était donc renvoyé, pour se heurter à un 403.
     *
     * On ne la conserve que si elle lui est accessible : un client qui remplit son
     * panier puis se connecte doit bien revenir à son panier.
     */
    protected function authenticated(Request $request, $user)
    {
        $memorisee = $request->session()->get('url.intended');

        if ($memorisee && ! $this->peutAcceder($memorisee, $user)) {
            $request->session()->forget('url.intended');
        }

        return null;
    }

    /**
     * Limité aux deux espaces protégés : le reste du site est public ou défendu
     * par ses propres middlewares.
     */
    private function peutAcceder(string $url, $user): bool
    {
        $chemin = trim(parse_url($url, PHP_URL_PATH) ?: '/', '/');

        if (str_starts_with($chemin, 'dashboard')) {
            return in_array($user->role, EnsureUserIsStaff::ROLES, true);
        }

        if (str_starts_with($chemin, 'merchand')) {
            return Shop::where('id_user', $user->id)->exists();
        }

        return true;
    }

    public function redirectTo(): string
    {
        $user = Auth::user();

        if (! $user) {
            return '/';
        }

        if (in_array($user->role, EnsureUserIsStaff::ROLES, true)) {
            return route('admin.index');
        }

        if (Shop::where('id_user', $user->id)->exists()) {
            return route('merchand.index');
        }

        return '/';
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
