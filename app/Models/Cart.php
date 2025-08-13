<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\order_detail;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','status','total_amount'];

    // Relation avec le modèle User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cart_items()
{
    return $this->hasMany(CartItem::class, 'cart_id');
}

    // Relation avec les éléments du panier
    public function cartItems()
    {
        return $this->hasMany(CartItem::class,'cart_id');
    }

    // Méthode pour calculer le montant total du panier
    public function getTotalAmount()
    {
        return $this->cartItems->sum(function ($cartItem) {
            return $cartItem->quantity * $cartItem->product->price;
        });
    }

    // Méthode pour vider le panier
    public function clearCart()
    {
        $this->cartItems()->delete();
    }

public function product()
{
    return $this->belongsTo(Product::class, 'product_id');
}

    public function orderDetails()
{
    return $this->hasMany(order_detail::class,'id_cart');
}
}

