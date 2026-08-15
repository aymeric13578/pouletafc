<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    /*
    | « status » manquait à cette liste alors que CartController le passe à
    | chaque ajout au panier : il était donc silencieusement écarté, et la
    | colonne restait à sa valeur par défaut. Ajouté pour que ce qui est écrit
    | soit ce qui est demandé.
    */
    protected $fillable = ['product_id', 'cart_id', 'quantity', 'price', 'amount', 'user_id', 'status'];

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

