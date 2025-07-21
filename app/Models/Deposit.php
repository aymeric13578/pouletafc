<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;
    
    protected $fillable =[
        "id_agent",
        "amount",
        "status",
        "num_transaction",
        "access_token",
        "scope",
        "expires_in",
        "id_operator",
        "paytoken"
        
        
        ];
}
