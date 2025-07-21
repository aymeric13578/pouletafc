<?php

namespace App\Http\Controllers\Merchand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Seller;
use App\Models\Shop;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

class SellerController extends Controller
{

    public function sellerList()
    {
        $sellers = Seller::whereNull('deleted_at')->latest()->get();
        $shops = Shop::whereNull('deleted_at')->latest()->get();

        return view('merchand.sellers.seller_list', compact('sellers', 'shops'));
    }

    public function addSeller()
    {
        $shops = Shop::whereNull('deleted_at')->latest()->get();

        return view('merchand.sellers.add_seller', compact('shops'));
    }

    public function storeSeller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "seller_full_name" => 'required|unique:sellers',
            "shop_id" => 'required'
        ]);

        if ($validator->fails()) {
            Session::flash("danger", "Le nom et l'id de la boutique sont obligatoires, veuillez tous les remplir ! ");
            return back()->with('message', "Erreur de validation des données !");
        }

        $seller_photo = $request->file('seller_photo');
        $seller_photo_name = hexdec(uniqid()) . '.' . $seller_photo->getClientOriginalExtension();
        $request->{'seller_photo'}->move(public_path('upload'), $seller_photo_name);
        $seller_photo_url = 'upload/' . $seller_photo_name;

        Seller::insert([
            'seller_full_name' => $request->{'seller_full_name'},
            'seller_code' => $request->{'seller_code'},
            'seller_telephone1' => $request->{'seller_telephone1'},
            'seller_telephone2' => $request->{'seller_telephone2'},
            'seller_telephone3' => $request->{'seller_telephone3'},
            'seller_address' => $request->{'seller_address'},
            'seller_email1' => $request->{'seller_email1'},
            'seller_email2' => $request->{'seller_email2'},
            'shop_id' => $request->{'shop_id'},
            'seller_photo' => $seller_photo_url,
            'seller_niu' => $request->{'seller_niu'},
            'slug' => strtolower(str_replace(' ', '-', $request->{'shop_name'})),
        ]);

        return redirect()->route('sellerlist')->with('message', 'Marchand ajouté avec succès!');
    }

    public function editSeller(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};

            $seller = Seller::findOrFail($id);

            /* $seller_photo = $request->file('seller_photo');
            $seller_photo_name = hexdec(uniqid()) . '.' . $seller_photo->getClientOriginalExtension();
            $request->{'seller_photo'}->move(public_path('upload'), $seller_photo_name);
            $seller_photo_url = 'upload/' . $seller_photo_name; */

            $seller->update([
                'seller_full_name' => $request->{'name'},
                'seller_code' => $request->{'seller_code'},
                'seller_telephone1' => $request->{'seller_telephone1'},
                'seller_telephone2' => $request->{'seller_telephone2'},
                'seller_telephone3' => $request->{'seller_telephone3'},
                'seller_address' => $request->{'seller_address'},
                'seller_email1' => $request->{'seller_email1'},
                'seller_email2' => $request->{'seller_email2'},
                'shop_id' => $request->{'shop_id'},
                //'seller_photo' => $seller_photo_url,
                'seller_niu' => $request->{'seller_niu'},
                'slug' => strtolower(str_replace(' ', '-', $request->{'seller_full_name'})),
            ]);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }

    public function deleteSeller(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};
            $seller = Seller::findOrFail($id);

            $seller->delete();

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }
}
