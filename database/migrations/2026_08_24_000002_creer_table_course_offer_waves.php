<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vagues d'offre d'une course aux agents.
 *
 * getActiveCommand diffusait jusqu'ici la même course à tous les agents
 * libres en même temps : premier arrivé, premier servi, sans lien avec la
 * qualité de service ou la distance. Cette table enregistre, pour chaque
 * course et chaque agent candidat, à partir de quel instant (visible_at) cet
 * agent peut la voir — les mieux classés par DistributionScore ouvrent en
 * premier (vague 1), les suivants après un délai (vague 2, 3...).
 *
 * Un agent absent de cette table pour une course donnée ne la voit jamais :
 * l'absence de ligne est un signal (hors du pool de candidats), pas un
 * défaut d'ouverture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_offer_waves')) {
            return;
        }

        Schema::create('course_offer_waves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_clando')->nullable();
            $table->unsignedBigInteger('id_order')->nullable();
            $table->unsignedTinyInteger('wave');
            $table->decimal('score', 6, 2);
            $table->timestamp('visible_at');
            $table->timestamps();

            $table->index(['id_clando', 'id_user']);
            $table->index(['id_order', 'id_user']);
            $table->index('id_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offer_waves');
    }
};
