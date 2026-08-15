<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Droit d'accès d'un employé à un menu du tableau de bord.
 *
 * La clé « menu » est le nom de la route : c'est ce qui permet de vérifier un
 * droit à partir de la page demandée, sans correspondance à tenir à jour.
 */
class MenuPermission extends Model
{
    protected $table = 'menu_permissions';

    protected $fillable = ['user_id', 'menu'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
