<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clando extends Model
{
    use HasFactory;

    protected $table = 'clando';

    protected $fillable = [
        'ref',
        'id_user',
        'id_agent',
        'latMyPosition',
        'lonMyPosition',
        'latAgent',
        'lonAgent',
        'latDestination',
        'lonDestination',
        'status',
        'price',
        'times',
        'distance',
        'destinationName',
        'type',
        'vehicule',
        'matricule_vehicule',
        'delivery_type',
        'id_order',
        'commission_seller',
        'commission_agent'
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }
    
    public function users()
    {
         return $this->belongsTo(User::class, 'id_user');
    }
}