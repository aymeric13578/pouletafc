<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Commentaire laissé par un client sur une prestation.
 *
 * Distinct d'une note : on peut commenter sans juger, et plusieurs fois.
 */
class Commentaire extends Model
{
    protected $table = 'commentaires';

    protected $fillable = [
        'id_user',
        'id_agent',
        'id_order',
        'id_clando',
        'contenu',
        'reponse',
        'repondu_le',
        'masque',
    ];

    protected $casts = [
        'masque' => 'boolean',
        'repondu_le' => 'datetime',
    ];

    /**
     * Auteur du commentaire.
     *
     * Nommé « client » et non « user » : la table porte deux identifiants
     * d'utilisateur, celui qui parle et celui dont on parle.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

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

    /** Commentaires effectivement affichables. */
    public function scopeVisibles($query)
    {
        return $query->where('masque', false);
    }
}
