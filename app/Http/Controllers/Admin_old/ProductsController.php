<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductsController extends Controller
{
    public function index()
    {

        $products =  Product::with('shop','category','subCategory')->get();
        return Inertia::render('Admin/Page/Products/ListProducts',['products'=>$products]);
    }
}
