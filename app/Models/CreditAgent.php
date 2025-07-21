<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditAgent extends Model
{
    use HasFactory;

    protected $table = 'credit_agents';

    protected $fillable = [
        'id_user', // Utilisateur qui crédite
        'id_agent', // id_user de l'agent
        'amount',
        'created_at',
       
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}