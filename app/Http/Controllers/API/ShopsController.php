<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopsController extends Controller
{
    public function getAllShops()
    {
        $shops = Shop::where('status', 'Success')->get();

        return response()->json([
            'response' => 200,
            /*
             | Champs choisis plutôt que le modèle brut : commercial_register
             | et commercial_register_file (pièces d'identification légale du
             | marchand) ne doivent pas partir sur un endpoint sans
             | authentification consulté par n'importe quel client. is_open_now
             | est calculé ici plutôt que renvoyé tel quel : voir
             | Shop::estOuverteMaintenant(), le badge « Ouvert »/« Fermé »
             | affiché côté client ne doit refléter que les horaires réels, pas
             | shop_status (déjà filtré à 'Success' par la requête ci-dessus).
             */
            'data' => $shops->map(fn (Shop $s) => [
                'id' => $s->id,
                'shop_name' => $s->shop_name,
                'ref' => $s->ref,
                'status' => $s->status,
                'city' => $s->city,
                'address' => $s->address,
                'phone1' => $s->phone1,
                'phone2' => $s->phone2,
                'email1' => $s->email1,
                'email2' => $s->email2,
                'description' => $s->description,
                'logo' => $s->logo,
                'banner' => $s->banner,
                'type' => $s->type,
                'product_count' => $s->product_count,
                'opening_hours' => $s->opening_hours,
                'is_open_now' => $s->estOuverteMaintenant(),
            ])->values(),
        ]);
    }
    public function addShop(Request $request)
    {
        $request->validate([
            "shop_name" => 'required|unique:shops'
        ]);

        $registry_file = $request->file('shop_commercial_register_file');
        //dd($request);
        $registry_file_name = hexdec(uniqid()) . '.' . $registry_file->getClientOriginalExtension();
        $request->{'shop_commercial_register_file'}->move(public_path('upload'), $registry_file_name);
        $registry_file_url = 'upload/' . $registry_file_name;

        $shop_logo = $request->file('shop_logo');
        $shop_logo_name = hexdec(uniqid()) . '.' . $shop_logo->getClientOriginalExtension();
        $request->{'shop_logo'}->move(public_path('upload'), $shop_logo_name);
        $shop_logo_url = 'upload/' . $shop_logo_name;

        Shop::insert([
            'shop_name' => $request->{'shop_name'},
            'shop_code' => $request->{'shop_code'},
            'shop_brand' => $request->{'shop_brand'},
            'shop_telephone1' => $request->{'shop_telephone1'},
            'shop_address' => $request->{'shop_address'},
            'shop_email1' => $request->{'shop_email1'},
            'shop_commercial_register_file' => $registry_file_url,
            'shop_logo' => $shop_logo_url,
            'shop_commercial_register' => $request->{'shop_commercial_register'},
            'slug' => strtolower(str_replace(' ', '-', $request->{'shop_name'})),
        ]);

        return redirect()->route('shoplist')->with('message', 'Boutique ajoutée avec succès!');
    }

    public function editShop(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};

            $shop = Shop::findOrFail($id);

            $shop->update([
                'shop_name' => $request->{'shop_name'},
                'shop_code' => $request->{'shop_code'},
                'shop_brand' => $request->{'shop_brand'},
                'shop_telephone1' => $request->{'shop_telephone1'},
                'shop_address' => $request->{'shop_address'},
                'shop_email1' => $request->{'shop_email1'},
                'shop_commercial_register' => $request->{'shop_commercial_register'},
                'slug' => strtolower(str_replace(' ', '-', $request->{'shop_name'})),
            ]);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }

    public function deleteShop(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};
            $shop = Shop::findOrFail($id);

            $shop->delete();

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }
}
