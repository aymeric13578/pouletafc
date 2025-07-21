<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    use HasFactory;

    protected $fillable = [
        'clando_kilometer',
        'command_kilometer',
        'min_price_clando',
        'min_price_command',
        'clando_agent_commission',
        'clando_agent_command',
        'vip_percentage',
        'status'
    ];
}