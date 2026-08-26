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

    /**
     * Déléguée à Fonction::solde() — c'était auparavant une formule
     * indépendante (total_credited - toutes les courses/commandes, sans
     * filtre de statut, sans les dépôts) qui divergeait de Fonction::solde()
     * pour le même agent : deux écrans du tableau de bord affichaient deux
     * soldes différents pour la même personne. Une seule formule désormais,
     * appliquée partout où "le solde d'un agent" est demandé.
     */
    public function getBalanceAttribute()
    {
        return (new \App\Fonction\Fonction())->solde($this->id_user)['solde'];
    }
}