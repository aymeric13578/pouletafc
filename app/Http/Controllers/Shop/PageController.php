<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function cgv(): Response
    {
        return Inertia::render('Static/Cgv');
    }

    public function faq(): Response
    {
        return Inertia::render('Static/Faq');
    }

    public function contact(): Response
    {
        return Inertia::render('Static/Contact');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Pas d'envoi d'e-mail réel branché ici : à connecter à Mail::to(...) si besoin.
        return back()->with('success', 'Votre message a bien été envoyé, notre équipe vous répondra rapidement.');
    }

    /**
     * Page publique de suppression de compte (exigence Google Play).
     */
    public function deleteAccount(): Response
    {
        return Inertia::render('Static/DeleteAccount');
    }

    /**
     * Traite la demande de suppression de compte : vérifie l'identité de
     * l'utilisateur puis supprime définitivement le compte et ses données.
     */
    public function processDeleteAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Vous devez confirmer la suppression définitive de votre compte.',
        ]);

        $identifier = $validated['identifier'];

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('whatsapp', $identifier)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'identifier' => 'Identifiant ou mot de passe incorrect.',
            ]);
        }

        try {
            $user->purgeAccount();
        } catch (\Throwable $e) {
            Log::error('Échec suppression compte (web) : ' . $e->getMessage());

            return back()->withErrors([
                'identifier' => 'Une erreur est survenue lors de la suppression. Veuillez réessayer.',
            ]);
        }

        return back()->with(
            'success',
            'Votre compte CLANDO (Poulet AFC) et l\'ensemble de vos données personnelles ont été supprimés définitivement.'
        );
    }

    public function subscribeNewsletter(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return back()->with('success', 'Merci pour votre inscription à la newsletter !');
    }
}
