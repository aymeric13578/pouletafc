<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

     protected $fillable = [
        'amount',
        'id_order_details',
        'id_user',
        'status',
        'access_token',
        'scope',
        'expires_in',
        'id_operator',
        'paytoken',
        'id_agent',
        'amount',
        'num_transaction',
        // Quelle table id_order_details désigne réellement — 'order_details'
        // (défaut, commandes boutique et courses coursier) ou 'clando'
        // (courses moto). Sans ça, verifiedOrangePaymentStatus n'avait
        // aucun moyen de savoir où écrire le paiement confirmé.
        'order_type'

    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function orderDetail()
    {
        return $this->belongsTo(order_detail::class, 'id_order_details');
    }
}
