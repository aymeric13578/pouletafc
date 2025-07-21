<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeginAgentDay extends Model
{
    use HasFactory;
    
     protected $fillable = ['id_user','status','lat','lon','type'];
}
