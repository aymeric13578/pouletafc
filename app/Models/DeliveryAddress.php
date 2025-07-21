<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'address',
        'status',
      

    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
