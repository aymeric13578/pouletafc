<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = order_detail::where('id_user', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate(10);

        $orders->getCollection()->transform(fn (order_detail $order) => $this->summarize($order));

        return Inertia::render('Client/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, string $ref): Response
    {
        $order = order_detail::where('ref', $ref)
            ->where('id_user', $request->user()->id)
            ->with('carts.cartItems.product')
            ->firstOrFail();

        return Inertia::render('Client/Orders/Show', [
            'order' => [
                ...$this->summarize($order),
                'address' => $order->address,
                'phone_customer' => $order->phone_customer,
                'payment_method' => $order->payment_method,
                'items' => optional($order->carts)?->cartItems?->map(fn ($item) => [
                    'name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'amount' => (int) $item->amount,
                ])->values() ?? [],
            ],
        ]);
    }

    protected function summarize(order_detail $order): array
    {
        return [
            'ref' => $order->ref,
            'status' => $order->status,
            'status_paiement' => $order->status_paiement,
            'price' => (int) $order->price,
            'delivery_type' => $order->delivery_type,
            'date' => optional($order->created_at)->translatedFormat('d F Y'),
        ];
    }
}
