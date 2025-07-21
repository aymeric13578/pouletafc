<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class SalerShopController extends Controller
{
    public function getSalerShop(Request $request)
    {
        $shops = Shop::all();
        return response()->json([
            "response"=>200,
            "data"=>$shops->where('seller_id',$request->id),

        ]);
    }
}
