<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quarter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'id_user',
    ];

    public function locations()
    {
        return $this->hasMany(Location::class, 'id_quarter');
    }
}
