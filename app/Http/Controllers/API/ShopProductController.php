<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;

class ShopProductController extends Controller
{
    public function getShopProduct(Request $request)
    {
        $product = Product::where('id_shop',$request->id)->get();

        // Même enrichissement que ProductsController::getAllProducts, sinon
        // une promotion validée reste invisible sur la page boutique alors
        // qu'elle apparaît déjà sur l'accueil.
        $promotions = Promotion::where('status', 'Success')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get()
            ->keyBy('id_product');

        $data = $product->map(function ($item) use ($promotions) {
            $array = $item->toArray();
            $promotion = $promotions->get($item->id);
            if ($promotion) {
                $array['promotion'] = [
                    'title' => $promotion->title,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'price_after' => round($promotion->prixApres((float) $item->price)),
                ];
            }
            return $array;
        });

        return response()->json([
            "response"=>200,
            "data"=>$data,

        ]);
    }
}
