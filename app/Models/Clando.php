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
        'commission_agent',
        'status_paiement',
        'payment_method',
        'base_price',
        'stops_surcharge'
    ];

    public function stops()
    {
        return $this->hasMany(ClandoStop::class, 'id_clando')->orderBy('id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }
    
    public function users()
    {
         return $this->belongsTo(User::class, 'id_user');
    }
}