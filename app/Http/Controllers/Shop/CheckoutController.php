<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\order_detail;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public const DELIVERY_METHODS = [
        'standard' => ['label' => 'Livraison standard', 'description' => 'Sous 24 à 48h', 'fee' => 1000],
        'express' => ['label' => 'Livraison express', 'description' => 'Sous 2h, zones urbaines', 'fee' => 2000],
        'pickup' => ['label' => 'Retrait en magasin', 'description' => 'Gratuit, disponible sous 1h', 'fee' => 0],
    ];

    public const PAYMENT_METHODS = [
        'card' => 'Carte bancaire',
        'mobile_money' => 'Mobile Money (Orange / MTN)',
        'cash_on_delivery' => 'Paiement à la livraison',
    ];

    public function __construct(protected CartService $cart)
    {
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $items = $this->cart->items();

        if (empty($items)) {
            return redirect()->route('shop.cart.index')->with('error', 'Votre panier est vide.');
        }

        $user = $request->user();

        $addresses = DeliveryAddress::where('id_user', $user->id)->latest('id')->get(['id', 'address']);

        return Inertia::render('Checkout/Index', [
            'items' => $items,
            'subtotal' => collect($items)->sum('line_total'),
            'addresses' => $addresses,
            'deliveryMethods' => self::DELIVERY_METHODS,
            'paymentMethods' => self::PAYMENT_METHODS,
            'user' => [
                'name' => trim($user->name.' '.$user->last_name),
                'phone' => $user->phone,
                'email' => $user->email,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $items = $this->cart->items();

        if (empty($items)) {
            return redirect()->route('shop.cart.index')->with('error', 'Votre panier est vide.');
        }

        $data = $request->validate([
            'address_id' => ['nullable', 'integer', 'exists:delivery_addresses,id'],
            'new_address' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'delivery_method' => ['required', Rule::in(array_keys(self::DELIVERY_METHODS))],
            'payment_method' => ['required', Rule::in(array_keys(self::PAYMENT_METHODS))],
        ]);

        $user = $request->user();

        $address = $data['new_address'] ?? optional(
            DeliveryAddress::where('id_user', $user->id)->find($data['address_id'] ?? null)
        )->address;

        if (! empty($data['new_address'])) {
            DeliveryAddress::create([
                'id_user' => $user->id,
                'address' => $data['new_address'],
                'status' => 'Success',
            ]);
        }

        $subtotal = collect($items)->sum('line_total');
        $deliveryFee = self::DELIVERY_METHODS[$data['delivery_method']]['fee'];
        $total = $subtotal + $deliveryFee;

        $dbCart = Cart::create([
            'user_id' => $user->id,
            'status' => 'Success',
            'total_amount' => $total,
        ]);

        foreach ($items as $item) {
            CartItem::create([
                'cart_id' => $dbCart->id,
                'product_id' => $item['product']['id'],
                'user_id' => $user->id,
                'quantity' => $item['quantity'],
                'amount' => $item['product']['price'],
                'status' => 'Success',
            ]);
        }

        do {
            $ref = 'CMD'.strtoupper(Str::random(8));
        } while (order_detail::where('ref', $ref)->exists());

        $order = order_detail::create([
            'id_user' => $user->id,
            'id_cart' => $dbCart->id,
            'ref' => $ref,
            'qty' => collect($items)->sum('quantity'),
            'price' => $total,
            'panier_price' => $subtotal,
            'delivery_fees' => $deliveryFee,
            'delivery_type' => $data['delivery_method'],
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            /*
             | La colonne est un enum('pending','Success','failed'). Les valeurs
             | 'unpaid' / 'paid' écrites ici n'en font pas partie : MySQL les refuse
             | en mode strict, ou les remplace par une chaîne vide sinon. Aucune
             | commande du site n'avait donc de statut de paiement exploitable.
             */
            'status_paiement' => $data['payment_method'] === 'cash_on_delivery' ? 'pending' : 'Success',
            'address' => $address,
            'phone_customer' => $data['phone'],
            'email_customer' => $user->email,
            'date' => now(),
            'delivery_code' => (string) random_int(1000, 9999),
        ]);

        $this->cart->clear();

        return redirect()->route('shop.checkout.confirmation', $order->ref);
    }

    public function confirmation(Request $request, string $ref): Response
    {
        $order = order_detail::where('ref', $ref)
            ->where('id_user', $request->user()->id)
            ->with('carts.cartItems.product')
            ->firstOrFail();

        return Inertia::render('Checkout/Confirmation', [
            'order' => $this->transformOrder($order),
        ]);
    }

    protected function transformOrder(order_detail $order): array
    {
        return [
            'ref' => $order->ref,
            'status' => $order->status,
            'status_paiement' => $order->status_paiement,
            'delivery_type' => $order->delivery_type,
            'payment_method' => $order->payment_method,
            'price' => (int) $order->price,
            'panier_price' => (int) $order->panier_price,
            'delivery_fees' => (int) $order->delivery_fees,
            'address' => $order->address,
            'date' => optional($order->created_at)->toDateTimeString(),
            'items' => optional($order->carts)?->cartItems?->map(fn (CartItem $item) => [
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'amount' => (int) $item->amount,
            ])->values() ?? [],
        ];
    }
}
