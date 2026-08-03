<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_quarter',
        'name',
        'status',
        'id_user',
        'longitude',
        'latitude',
    ];

    /**
     * Certaines locations pointent vers un id_quarter inexistant : la relation
     * peut donc être nulle, et l'application cliente le prévoit.
     */
    public function quarter()
    {
        return $this->belongsTo(Quarter::class, 'id_quarter');
    }

    /**
     * Agent ou employé qui a enregistré le lieu depuis l'application mobile.
     * Nullable : les lieux les plus anciens n'ont pas d'auteur renseigné.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
