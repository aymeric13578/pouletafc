<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'ref',
        'id_customer',
        'method',
        'status',
        'id_agent',
        'id_operator',
        'price',
        "status_paiement",
        "status_delivery",
        "id_cart",
        "id_delivery_address"
       
    ];
}
