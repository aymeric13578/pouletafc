<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalEnrollment extends Model
{
    protected $fillable = [
        'campaign_id', 'agent_id', 'option_id', 'enrolled_at', 'locked_at', 'auto_assigned',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'locked_at' => 'datetime',
        'auto_assigned' => 'boolean',
    ];

    public function option()
    {
        return $this->belongsTo(GoalOption::class, 'option_id');
    }
}
