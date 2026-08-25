<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClandoStop extends Model
{
    protected $table = 'clando_stops';

    protected $fillable = [
        'id_clando',
        'lat',
        'lon',
        'label',
        'type',
    ];

    public function clando()
    {
        return $this->belongsTo(Clando::class, 'id_clando');
    }
}
