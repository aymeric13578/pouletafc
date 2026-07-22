<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $addresses = DeliveryAddress::where('id_user', $request->user()->id)
            ->latest('id')
            ->get(['id', 'address', 'status']);

        return Inertia::render('Client/Addresses/Index', [
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
        ]);

        DeliveryAddress::create([
            'id_user' => $request->user()->id,
            'address' => $data['address'],
            'status' => 'Success',
        ]);

        return back()->with('success', 'Adresse ajoutée.');
    }

    public function destroy(Request $request, DeliveryAddress $address): RedirectResponse
    {
        abort_unless($address->id_user === $request->user()->id, 403);

        $address->delete();

        return back()->with('success', 'Adresse supprimée.');
    }
}
