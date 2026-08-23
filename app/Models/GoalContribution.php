<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalContribution extends Model
{
    protected $fillable = ['campaign_id', 'agent_id', 'ride_ref', 'counted_at'];

    protected $casts = [
        'counted_at' => 'datetime',
    ];
}
