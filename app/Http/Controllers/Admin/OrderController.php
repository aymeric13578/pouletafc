<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\order_detail;
use DB;

class OrderController extends Controller
{
    public function orderList()
    {
        $orders = order_detail::with('user')
            ->orderBy('id', 'desc')
            ->where('status', '!=', 'failed')
            ->get();
        return view('admin.orders.order_list', ['orders' => $orders]);
    }

    public function checkNewOrder(Request $request)
    {
        $lastOrderId = $request->query('lastOrderId', 0);

        // Récupérer la commande la plus récente
        $latestOrder = order_detail::orderBy('id', 'desc')
            ->where('status', '!=', 'failed')
            ->first();

        if ($latestOrder && $latestOrder->id > $lastOrderId) {
            return response()->json([
                'hasNewOrder' => true,
                'newOrderId' => $latestOrder->id,
            ]);
        }

        return response()->json([
            'hasNewOrder' => false,
        ]);
    }

    public function getNewOrder(Request $request)
    {
        $orderId = $request->query('orderId');

        $order = order_detail::with('user')
            ->where('id', $orderId)
            ->where('status', '!=', 'failed')
            ->first();

        if ($order) {
            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'ref' => $order->ref,
                    'address' => $order->address,
                    'user_name' => $order->user->name,
                    'user_phone' => $order->user->phone,
                    'status' => $order->status,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
        ]);
    }
}