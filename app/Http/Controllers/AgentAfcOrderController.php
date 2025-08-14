<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\order_detail;

class AgentAfcOrderController extends Controller
{
    public function checkNewOrders(Request $request)
    {
        $lastOrderId = $request->query('lastOrderId', 0);
        $latestOrder = order_detail::orderBy('id', 'desc')->first();

        if ($latestOrder && $latestOrder->id > $lastOrderId) {
            return response()->json([
                'hasNewOrder' => true,
                'newOrderId' => $latestOrder->id,
            ]);
        }

        return response()->json(['hasNewOrder' => false]);
    }

    public function getNewOrder(Request $request)
    {
        $orderId = $request->query('orderId');
        $order = order_detail::with('user')->find($orderId);

        if ($order) {
            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'address' => $order->address,
                    'user_name' => $order->user ? $order->user->name : 'N/A',
                    'user_phone' => $order->user ? $order->user->phone : 'N/A',
                    'status' => $order->status,
                ],
            ]);
        }

        return response()->json(['success' => false], 404);
    }
}
