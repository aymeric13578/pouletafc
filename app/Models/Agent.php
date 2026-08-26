<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\order_detail;
class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'agent_name',
        'phone',
        'national_identity_card_number',
        'photo',
        'identity_card_file',
        'location_plan_file',
        'registration_number',
        'status',
        'balance',
        'deposit_recu',
        'total_credited',
        'id_user',
        'ref',
        'longitude',
        'latitude',
        'type',
        'vehicule',
        'matricule_vehicule'
    ];
    
    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class, "id_agent");
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
    public function cautions()
    {
        return $this->hasMany(Agent::class, 'id_agent');
    }

    public function creditAgents()
    {
        return $this->hasMany(CreditAgent::class, 'id_agent', 'id_user');
    }

    public function clandos()
    {
        return $this->hasMany(Clando::class, 'id_agent', 'id_user');
    }

    public function orderDetails()
    {
        return $this->hasMany(order_detail::class, 'id_agent', 'id_user');
    }

    public function getTotalCreditedAttribute()
    {
        return $this->creditAgents()->sum('amount');
    }

    public function getTotalEarnedAttribute()
    {
        $clandoCommission = $this->clandos()->sum('commission_agent');
        $orderCommission = $this->orderDetails()->sum('commission_agent');
        return $clandoCommission + $orderCommission;
    }

    public function getBalanceAttribute()
    {
        $totalSpentClando = $this->clandos()->sum('price');
        $totalSpentOrder = $this->orderDetails()->sum('price');
        return $this->total_credited - ($totalSpentClando + $totalSpentOrder);
    }
}