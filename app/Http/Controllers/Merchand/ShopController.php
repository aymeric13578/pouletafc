<?php

namespace App\Http\Controllers\Merchand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Auth;


class ShopController extends Controller
{

    public function shopList()
    {
        $shops = DB::table('shops')
            ->orderBy('id', 'desc')
            ->get();
        return view('merchand.shops.shop_list', ['shops' => $shops]);
    }

    public function addShop()
    {
        return view('merchand.shops.add_shop');
    }

    public function storeshop(Request $request)
    {
        $request->validate([
            "shop_name" => 'required|unique:shops'
        ]);

        $registry_file = $request->file('shop_commercial_register_file');
        //dd($request);
        $registry_file_name = hexdec(uniqid()) . '.' . $registry_file->extension();
        $request->{'shop_commercial_register_file'}->move(public_path('upload'), $registry_file_name);
        $registry_file_url = 'upload/' . $registry_file_name;

        $shop_logo = $request->file('shop_logo');
        $shop_logo_name = hexdec(uniqid()) . '.' . $shop_logo->extension();
        $request->{'shop_logo'}->move(public_path('upload'), $shop_logo_name);
        $shop_logo_url = 'upload/' . $shop_logo_name;



        $shop_banner = $request->file('banner');
        $shop_banner_name = hexdec(uniqid()) . '.' . $shop_banner->extension();
        $request->{'banner'}->move(public_path('upload'), $shop_banner_name);
        $shop_banner_url = 'upload/' . $shop_banner_name;

        Shop::insert([
            'shop_name' => $request->{'shop_name'},
            'id_user'=> Auth::user()->id, 
            'ref' => $request->{'shop_code'},
            'banner' => $shop_banner_url,
            'type' => $request->{'type'},
            'phone1' => $request->{'shop_telephone1'},
            'email1' => $request->{'shop_email1'},
            'logo' => $shop_logo_url,
            'slug' => strtolower(str_replace(' ', '-', $request->{'shop_name'})),
            'commercial_register' => $request->{'shop_commercial_register'},
            'commercial_register_file' => $registry_file_url,
            'description' =>$request->{'description'},
            'address' => $request->{'shop_address'},
        ]);

        session()->flash("good","Boutique ajoutée avec success");


        return redirect()->route('merchandshoplist')->with('message', 'Boutique ajoutée avec succès!');
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
