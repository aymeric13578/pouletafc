<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons de déverrouillage des écrans "mur" sans authentification
 * (/commandes, /clando, /commandes/carte) — voir App\Support\KioskLock et
 * docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kiosk_unlock_tokens')) {
            return;
        }

        Schema::create('kiosk_unlock_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('page', 30);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedBigInteger('unlocked_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['page', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_unlock_tokens');
    }
};
