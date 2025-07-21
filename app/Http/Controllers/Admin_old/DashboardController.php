<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {


        $Orders = Order::all();
      
        $top5BestProducts = $Orders->where('status','Success')
        ->select('count(*) as nbrCommande,product_name,city_shop,name_shop,id_product')
        ->groupBy('id_product');

        $users = User::where('status','Success')->get();
        return Inertia::render(
            'Admin/Page/Index',
            [
                'progressOrders' => $Orders->where('status', 'progress'),
                'successOrders' => $Orders->where('status', 'Success'),
                'failedOrders' => $Orders->where('status', 'failed'),
                'nbrProgressOrders'=>count($Orders->where('status', 'progress')),
                'top5BestProducts',
                'completeOrders'=>count($Orders->where('status', 'progress')),
                'users'=>count($users)
            ]
        );
    }
}
