<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalCampaign extends Model
{
    protected $fillable = [
        'title', 'metric', 'ride_kind', 'starts_at', 'ends_at',
        'enrollment_closes_at', 'default_option_id', 'zone_id',
        'agency_id', 'status', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'enrollment_closes_at' => 'datetime',
    ];

    public function options()
    {
        return $this->hasMany(GoalOption::class, 'campaign_id')->orderBy('position');
    }

    public function enrollments()
    {
        return $this->hasMany(GoalEnrollment::class, 'campaign_id');
    }
}
