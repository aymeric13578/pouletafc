<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;
    
    protected $table = "note";
    
      protected $fillable = [
        'id_user',
        'id_agent',
        'note',
        'id_clando',
        'id_order',
        'comment',
        'reasons'
    ];

    protected $casts = [
        'reasons' => 'array',
    ];

    /**
     * Client qui a laissé l'appréciation.
     *
     * Nommée « client » et non « user » : la table porte deux identifiants
     * d'utilisateur, celui qui note et celui qui est noté. Un nom neutre ferait
     * confondre les deux au premier coup d'œil.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /** Agent noté. */
    public function agent()
    {
        return $this->belongsTo(User::class, 'id_agent');
    }

    public function commande()
    {
        return $this->belongsTo(order_detail::class, 'id_order');
    }

    public function course()
    {
        return $this->belongsTo(Clando::class, 'id_clando');
    }
}
