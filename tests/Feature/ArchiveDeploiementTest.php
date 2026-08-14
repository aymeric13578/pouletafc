<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Contenu de l'archive de déploiement.
 *
 * Elle transportait 108 Mo inutiles à chaque déploiement : les images du
 * back-office, qui vivent sur le serveur et y sont restaurées depuis la
 * sauvegarde, les maquettes HTML d'un thème que rien ne référence, et un dump
 * SQL. L'envoi durait six minutes, sur des déploiements de vingt.
 *
 * Ce test garde les exclusions en place : les rajouter par mégarde ferait
 * regrimper le temps sans que personne ne s'en aperçoive avant longtemps.
 */
class ArchiveDeploiementTest extends TestCase
{
    private function workflow(): string
    {
        $chemin = base_path('.github/workflows/deploy.yml');

        if (! is_file($chemin)) {
            $this->markTestSkipped('Workflow de déploiement absent.');
        }

        return file_get_contents($chemin);
    }

    public function test_les_gros_dossiers_inutiles_restent_exclus(): void
    {
        $workflow = $this->workflow();

        foreach (["--exclude='public/upload'", "--exclude='template'", "--exclude='*.sql'"] as $exclusion) {
            $this->assertStringContainsString($exclusion, $workflow, "Exclusion perdue : $exclusion");
        }
    }

    public function test_les_maquettes_du_theme_ne_sont_toujours_referencees_nulle_part(): void
    {
        /*
         * L'exclusion de « template » ne tient que tant que rien ne s'en sert.
         * Si une vue venait à pointer dessus, le dossier redeviendrait
         * nécessaire et son absence casserait la page en production.
         *
         * Les commentaires laissés par l'outil qui a aspiré le thème citent
         * « template/ » sans rien référencer : on ne retient que les usages
         * réels, via asset() ou une balise src/href.
         */
        $dossiers = [base_path('app'), base_path('routes'), base_path('config'), base_path('resources/views')];
        $usages = [];

        foreach ($dossiers as $dossier) {
            if (! is_dir($dossier)) {
                continue;
            }

            $fichiers = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier));

            foreach ($fichiers as $fichier) {
                if (! $fichier->isFile()) {
                    continue;
                }

                $contenu = file_get_contents($fichier->getPathname());

                if (preg_match('#(asset\(|src=|href=)[\'"]/?template/#', $contenu)) {
                    $usages[] = str_replace(base_path() . '/', '', $fichier->getPathname());
                }
            }
        }

        $this->assertSame([], $usages, "Le dossier template est de nouveau utilisé : l'exclure casserait ces pages.");
    }

    public function test_un_seul_deploiement_a_la_fois(): void
    {
        /*
         * Trois poussées rapprochées lançaient trois déploiements concurrents
         * sur les mêmes dossiers : le second déplaçait le dossier de release
         * pendant que le premier y extrayait, et tar échouait sur chaque chemin.
         */
        $workflow = $this->workflow();

        $this->assertStringContainsString('concurrency:', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
    }

    public function test_le_env_du_serveur_n_est_jamais_embarque(): void
    {
        // Il porte les mots de passe de production et ne doit pas voyager.
        $this->assertStringContainsString("--exclude='.env'", $this->workflow());
    }
}
