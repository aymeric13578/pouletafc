<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected string $sessionKey = 'cart';

    public function raw(): array
    {
        return Session::get($this->sessionKey, []);
    }

    public function items(): array
    {
        $raw = $this->raw();

        if (empty($raw)) {
            return [];
        }

        $products = Product::whereIn('id', array_keys($raw))->get()->keyBy('id');
        $items = [];

        foreach ($raw as $productId => $quantity) {
            $product = $products->get((int) $productId);

            if (! $product) {
                continue;
            }

            $price = (int) $product->price;

            $items[] = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $price,
                    'stock' => $product->stock_init !== null ? (int) $product->stock_init : null,
                    'image_url' => product_image_url($product->img ?: $product->product_image1),
                ],
                'quantity' => (int) $quantity,
                'line_total' => $price * (int) $quantity,
            ];
        }

        return $items;
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function subtotal(): int
    {
        return collect($this->items())->sum('line_total');
    }

    public function add(int $productId, int $quantity): void
    {
        $product = Product::findOrFail($productId);
        $cart = $this->raw();
        $quantity = ($cart[$productId] ?? 0) + max(1, $quantity);

        if ($product->stock_init !== null && (int) $product->stock_init > 0) {
            $quantity = min($quantity, (int) $product->stock_init);
        }

        $cart[$productId] = $quantity;
        Session::put($this->sessionKey, $cart);
    }

    public function update(int $productId, int $quantity): void
    {
        $cart = $this->raw();

        if ($quantity < 1) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }

        Session::put($this->sessionKey, $cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->raw();
        unset($cart[$productId]);
        Session::put($this->sessionKey, $cart);
    }

    public function clear(): void
    {
        Session::forget($this->sessionKey);
    }
}
