<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jeton de déverrouillage d'un écran "mur" — voir App\Support\KioskLock et la
 * migration 2026_08_27_000002_creer_table_kiosk_unlock_tokens.
 */
class KioskUnlockToken extends Model
{
    protected $table = 'kiosk_unlock_tokens';

    protected $fillable = [
        'page',
        'token',
        'expires_at',
        'unlocked_at',
        'unlocked_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];
}
