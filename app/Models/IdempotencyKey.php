<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une clé d'idempotence enregistrée — voir App\Support\Idempotence et la
 * migration 2026_08_27_000001_creer_table_idempotency_keys.
 */
class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'endpoint',
        'response_status',
        'response_body',
    ];
}
