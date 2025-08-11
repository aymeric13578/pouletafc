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
        'num_transaction'

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
