<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use Illuminate\Support\Facades\DB;


class StatisticController extends Controller
{

    public function livraisonList()
    {
        /* $livraisons = DB::table('livraisons')
            ->orderBy('id', 'desc')
            ->get(); */
        return view('admin.statistics.livraison-list', ['livraisons' => []]);
    }

    public function sellerList()
    {
        /* $sellers = DB::table('sellers')
            ->orderBy('id', 'desc')
            ->get(); */
        return view('admin.statistics.seller-list', ['sellers' => []]);
    }

    public function productList()
    {
        /* $products = DB::table('products')
            ->orderBy('id', 'desc')
            ->get(); */
        return view('admin.statistics.product-list', ['products' => []]);
    }
}
