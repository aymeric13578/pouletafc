<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order_detail extends Model
{
    use HasFactory;

    protected $table = 'order_details';

    protected $fillable = [
        'id_order',
        'product_name',
        'id_customer',
        'price',
        'qty',
        'subtotal',
        'discount',
        'tax',
        'total_ttc',
        'email_customer',
        'phone_customer',
        'date',
        'status',
        'id_cart',
        'longitude',
        'latitude',
        'id_user',
        'ref',
        'payment_method',
        'commission_seller',
        'id_agent',
        'delivery_type',
        'latAgent',
        'lonAgent',
        'matricule_vehicule',
        'address',
        'latShop',
        'lonShop',
        'shop_name',
        'delivery_code',
        'commission_agent'
    ];

    public function carts()
    {
        return $this->belongsTo(Cart::class, 'id_cart');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }
}