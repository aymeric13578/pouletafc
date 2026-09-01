<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /*
         | Primes d'objectifs : versées automatiquement à l'échéance de
         | chaque campagne (règle validée le 2026-09-01, voir la commande).
         | Chaque nuit suffit — une campagne se règle le lendemain de sa
         | date de fin au plus tard, et la commande est relançable à la
         | main sans risque (idempotente) si besoin d'aller plus vite.
         |
         | Ne tourne que si l'hébergeur exécute `schedule:run` (cron) : à
         | vérifier au déploiement — sinon, panel N0C → Crons.
         */
        $schedule->command('primes:verser')->dailyAt('02:00');

        // Abonnements boutique : chaque nuit aussi — la commande ne prélève
        // que les échéances arrivées (date par boutique, tableau de bord) et
        // rattrape seule un jour de cron manqué. Idempotente par échéance.
        $schedule->command('abonnements:prelever')->dailyAt('02:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
