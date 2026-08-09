<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aucun composant React ne doit porter le nom d'un objet global JavaScript.
 *
 * Le fichier Orders/Map.jsx exportait « function Map ». Ce nom masque le Map
 * natif dans tout le module : les `useRef(new Map())` de la page instanciaient
 * alors le composant lui-même, appelé comme constructeur sans props, et l'écran
 * mourait au chargement sur « Cannot destructure property 'initial' of
 * 'undefined' ».
 *
 * Rien ne le signale à la construction — Vite compile sans broncher, les tests
 * PHP passent, la page renvoie 200 puisque le rendu est côté navigateur. Le
 * défaut ne se voit qu'en ouvrant l'écran. D'où ce contrôle, qui lit simplement
 * les sources : le projet n'a pas d'outillage de test JavaScript, et en installer
 * un pour ce seul motif serait hors de proportion.
 *
 * Le nom du fichier peut, lui, rester Map.jsx : Inertia résout les pages par
 * chemin, jamais par le nom de la fonction exportée.
 */
class ComposantsSansCollisionTest extends TestCase
{
    /** Globals dont le masquage casse silencieusement du code courant. */
    private const GLOBAUX = [
        'Map', 'Set', 'WeakMap', 'Date', 'Array', 'Object', 'Promise', 'Error',
        'Number', 'String', 'Boolean', 'Symbol', 'Proxy', 'RegExp', 'Function',
        'Image', 'Text', 'Range', 'Event', 'Node', 'Element', 'Request',
        'Response', 'Headers', 'URL', 'Worker', 'History', 'Location', 'Screen',
        'Selection', 'Notification', 'Option', 'Audio', 'Video', 'Intl',
    ];

    public function test_aucun_composant_ne_masque_un_objet_global_javascript(): void
    {
        $collisions = [];

        foreach ($this->fichiersJsx() as $fichier) {
            $source = file_get_contents($fichier);

            preg_match_all('/^\s*(?:export\s+(?:default\s+)?)?function\s+(\w+)/m', $source, $trouves);

            foreach ($trouves[1] as $nom) {
                if (in_array($nom, self::GLOBAUX, true)) {
                    $collisions[] = sprintf(
                        '%s déclare « function %s », qui masque le %s natif',
                        str_replace(base_path() . '/', '', $fichier),
                        $nom,
                        $nom,
                    );
                }
            }
        }

        $this->assertSame([], $collisions, implode("\n", $collisions));
    }

    /**
     * @return array<int, string>
     */
    private function fichiersJsx(): array
    {
        $racine = resource_path('js');

        if (! is_dir($racine)) {
            return [];
        }

        $fichiers = [];
        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && $fichier->getExtension() === 'jsx') {
                $fichiers[] = $fichier->getPathname();
            }
        }

        return $fichiers;
    }
}
