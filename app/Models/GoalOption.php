<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalOption extends Model
{
    protected $fillable = ['campaign_id', 'label', 'threshold', 'reward', 'position'];
}
