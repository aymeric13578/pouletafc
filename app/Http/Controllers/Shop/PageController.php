<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function subscribeNewsletter(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return back()->with('success', 'Merci pour votre inscription à la newsletter !');
    }
}
