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

    /** Agent ou employé qui a créé le quartier depuis l'application mobile. */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
