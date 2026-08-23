<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Objectifs et primes — voir le document de conception fourni le 2026-08-23
 * (« Objectifs et primes — modèle de données et API »).
 *
 * Seul le côté « application agent » (lecture des campagnes + engagement) est
 * consommé pour l'instant par GoalController. La création/publication/clôture
 * de campagne côté dashboard n'a pas d'écran ni d'endpoint : ces tables
 * existent pour que ce travail futur ait un schéma prêt, mais tant qu'aucune
 * campagne n'est créée (à la main, ou via un futur tableau de bord),
 * getGoalCampaigns renvoie une liste vide — c'est le comportement honnête,
 * pas un bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goal_campaigns')) {
            Schema::create('goal_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('metric'); // rides, active_days, distance_km
                $table->string('ride_kind')->nullable(); // null = clando + livraison + course
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->dateTime('enrollment_closes_at');
                $table->unsignedBigInteger('default_option_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('status')->default('draft'); // draft, scheduled, running, closed, cancelled
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('goal_options')) {
            Schema::create('goal_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('goal_campaigns')->cascadeOnDelete();
                $table->string('label');
                $table->unsignedInteger('threshold');
                $table->unsignedInteger('reward');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('goal_enrollments')) {
            Schema::create('goal_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('goal_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('agent_id');
                $table->foreignId('option_id')->constrained('goal_options')->cascadeOnDelete();
                $table->dateTime('enrolled_at');
                $table->dateTime('locked_at')->nullable();
                $table->boolean('auto_assigned')->default(false);
                $table->timestamps();

                $table->unique(['campaign_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('goal_progress')) {
            Schema::create('goal_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('goal_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('agent_id');
                $table->unsignedInteger('progress')->default(0);
                $table->dateTime('achieved_at')->nullable();
                $table->unsignedInteger('frozen_progress')->nullable();
                $table->unsignedInteger('amount_due')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('goal_contributions')) {
            Schema::create('goal_contributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('goal_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('agent_id');
                $table->string('ride_ref');
                $table->dateTime('counted_at');
                $table->timestamps();

                $table->index(['campaign_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
        Schema::dropIfExists('goal_progress');
        Schema::dropIfExists('goal_enrollments');
        Schema::dropIfExists('goal_options');
        Schema::dropIfExists('goal_campaigns');
    }
};
