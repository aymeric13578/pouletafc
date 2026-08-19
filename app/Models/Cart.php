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

    /*
    | Les articles réellement dans le panier.
    |
    | Retirer un article ne l'efface pas : sa ligne passe en « failed ». Ces
    | relations ne filtraient rien, si bien que tout ce qui avait été retiré
    | continuait de s'afficher — sur le mur du comptoir, dans l'historique du
    | client, et jusque dans le détail du panier de l'application.
    |
    | Conséquence mesurée sur une commande du 19 août : treize lignes affichées
    | totalisant 30 000 F pour une commande de 2 500 F. Le montant était juste —
    | il ne comptait que les lignes vivantes — mais le comptoir voyait treize
    | articles à préparer, et le client treize articles à payer. Deux écrans qui
    | se contredisent, et personne pour savoir lequel croire.
    |
    | Le nom de la relation est repris tel quel aux deux endroits : elles sont
    | employées indifféremment dans le code, et n'ont aucune raison de ne pas
    | dire la même chose.
    */
    public function cart_items()
    {
        return $this->hasMany(CartItem::class, 'cart_id')->where('cart_items.status', 'Success');
    }

    // Relation avec les éléments du panier
    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'cart_id')->where('cart_items.status', 'Success');
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

