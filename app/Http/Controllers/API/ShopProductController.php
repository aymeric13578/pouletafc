<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopProductController extends Controller
{
    public function getShopProduct(Request $request)
    {
        $product = Product::where('id_shop',$request->id)->get();
        return response()->json([
            "response"=>200,
            "data"=>$product,

        ]);
    }
}
