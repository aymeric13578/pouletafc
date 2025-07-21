<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchand extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'status',
        'type',
        'ref',
        'contrat'

    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function shops()
    {
        return $this->hasMany(Shop::class, 'id_merchand');
    }

    public function products()
    {
        return $this->hasMany(Product::class,'id_merchand');
    }
}
