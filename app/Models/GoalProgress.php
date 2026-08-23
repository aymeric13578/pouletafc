<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalProgress extends Model
{
    protected $table = 'goal_progress';

    protected $fillable = [
        'campaign_id', 'agent_id', 'progress', 'achieved_at',
        'frozen_progress', 'amount_due', 'paid_at', 'paid_by',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];
}
