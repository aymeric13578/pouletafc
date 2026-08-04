<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Shop;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->destinationApresConnexion());
    }

    /**
     * Page d'arrivée après connexion, selon le profil.
     *
     * Tout le monde atterrissait sur la boutique publique : un marchand rattaché
     * à une boutique devait ensuite deviner l'URL de son espace pour la gérer.
     *
     * L'ordre compte. L'équipe interne passe avant le rattachement à une
     * boutique, car certains employés en possèdent une (« Poulet AFC » appartient
     * à un compte employee_afc) : sans cette priorité, ils seraient envoyés dans
     * l'espace marchand au lieu du tableau de bord.
     *
     * redirect()->intended respecte la page demandée avant la connexion : cette
     * destination ne s'applique qu'à défaut.
     */
    private function destinationApresConnexion(): string
    {
        $user = Auth::user();

        if ($user && in_array($user->role, EnsureUserIsStaff::ROLES, true)) {
            return route('admin.index');
        }

        if ($user && Shop::where('id_user', $user->id)->exists()) {
            return route('merchanddashboard');
        }

        return RouteServiceProvider::HOME;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
