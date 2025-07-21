<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caution extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cautions';

    protected $fillable = [
       
        'amount',
        'credited_by',
        'id_agent'
    ];

    public function agents()
    {
        return $this->belongsTo(Agent::class, 'id_agent');
    }

    // Relations
}
