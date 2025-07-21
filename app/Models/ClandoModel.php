<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClandoModel extends Model
{
    use HasFactory;
    
        protected $fillable = [
       'ref',
      'id_user',
      'id_agent',
      'latMyPosition',
      'lonMyPosition',
      'latAgent',
      'lonAgent',
      'latDestination',
      'lonDestination',
      'status'
    ];
}
