<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un signalement ou un enregistrement audio déclenché depuis le bouton
 * "panique" de l'écran de course — voir la migration
 * 2026_08_31_000001_creer_table_incidents_securite.php pour le contexte.
 */
class IncidentSecurite extends Model
{
    protected $table = 'incidents_securite';

    public const SIGNALEMENT = 'signalement';
    public const ENREGISTREMENT = 'enregistrement';

    public const NOUVEAU = 'nouveau';
    public const VU = 'vu';
    public const TRAITE = 'traite';

    protected $fillable = [
        'type_course',
        'id_clando',
        'id_order',
        'id_client',
        'id_agent',
        'type',
        'audio_path',
        'statut',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_client');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_agent');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Clando::class, 'id_clando');
    }
}
