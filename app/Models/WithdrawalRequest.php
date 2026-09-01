<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de retrait d'un agent — voir la migration
 * 2026_08_26_000004_creer_table_withdrawal_requests pour le contexte : pas
 * de virement automatique, seulement une demande à valider manuellement.
 */
class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $table = 'withdrawal_requests';

    protected $fillable = [
        'id_agent',
        'amount',
        // 'cash' | 'om' — comment l'agent veut être payé, et le numéro à
        // créditer pour Orange Money (voir la migration
        // 2026_09_01_000001_mode_et_numero_de_retrait).
        'mode',
        'phone',
        'status',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'amount' => 'float',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'id_agent', 'id_user');
    }
}
