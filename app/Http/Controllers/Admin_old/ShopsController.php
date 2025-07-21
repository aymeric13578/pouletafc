<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShopsController extends Controller
{
    public function index()
    {
        $shops = Shop::with('merchand.user')->get();
        return Inertia::render('Admin/Page/Shops/ListShops',['shops'=>$shops]);
    }
}
