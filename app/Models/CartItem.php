<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['product_id','cart_id', 'quantity', 'price','amount','user_id'];

    // Relation avec le modèle User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relation avec le modèle Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
        // Relation avec les éléments du panier
        public function cart()
        {
            return $this->hasMany(CartItem::class, 'cart_id');
        }
}

