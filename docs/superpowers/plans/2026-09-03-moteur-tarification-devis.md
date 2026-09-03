# Moteur de tarification côté serveur (`POST /api/v2/devis`) — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** Le prix d'une course (clando, livraison de boutique, course coursier) est calculé **par le serveur** à partir de la distance et du service, exposé par un endpoint de devis, et **recalculé à la création** de la course au lieu d'être accepté tel quel du téléphone.

**Architecture :** Un moteur pur `App\Support\Tarification` (sans HTTP, sans effet de bord) s'appuie sur `App\Support\GrilleTarifaire` (grille par service et plage horaire, ou repli sur `parameters`) et reproduit **exactement** les formules aujourd'hui codées dans `plouletafcapp` pour que la bascule ne change aucun prix affiché. Un contrôleur mince `DevisController` l'expose sous un nouveau préfixe `v2` (à part de `v1.0`, dont l'absence d'authentification par défaut ne doit pas se propager). `Insertclando` et `storeDeliveryOrder` recalculent le prix côté serveur dès que le client envoie une distance, et gardent le comportement actuel sinon (compatibilité avec les builds déjà installés).

**Tech Stack :** Laravel (PHP 8, Eloquent, PHPUnit via `php artisan test`), tables `tarifs`/`tarif_plages`/`parameters`.

**Spec :** pas de document séparé — la spécification tient dans la section §0 ci-dessous. Contexte : `C:\dev\ARCHITECTURE.md` §9 (grilles tarifaires) et `C:\dev\CLAUDE.md` règles 1, 3, 8, 10, 21.

## Global Constraints

- **Aucun prix affiché ne doit changer** tant qu'aucune grille par service n'existe : le repli doit reproduire les formules client à l'identique (voir §0.2), y compris l'arrondi au multiple de 50 supérieur.
- **Aucune route `v1.0` n'est renommée, supprimée ni ne change de méthode** (CLAUDE.md règle 1). `insertclando` et `storeDeliveryOrder` gardent leur nom, leurs paramètres et leur forme de réponse ; seuls des paramètres **optionnels** sont ajoutés.
- **Rétrocompatibilité des anciens builds** : un client qui n'envoie pas de distance obtient exactement le comportement d'aujourd'hui (prix client accepté, plancher à 1 F).
- **Pas de `RefreshDatabase`** : la suite tourne sur la base de développement (convention du dépôt, voir `tests/Feature/GrilleTarifaireTest.php`). Les tests de ce plan ne doivent dépendre **d'aucune table absente localement** : la base locale a **51 migrations en attente** (`php artisan migrate:status`), la table `clando` n'existe pas et `order_details` n'a pas ses colonnes récentes. Les tests injectent donc des doublures de `GrilleTarifaire` dans le conteneur et testent la logique de recalcul comme fonction pure.
- **Aucun secret** dans le code, les tests ou les docs (CLAUDE.md règle 21).
- **Aucun commit** : l'utilisateur committe lui-même. Chaque tâche se termine par `git status --short --branch` et `php artisan test --filter <Test>`.
- Langue du code, des commentaires et des tests : français, comme le reste du dépôt (`GrilleTarifaire`, `TarifPlage`, `Idempotence`…).
- Devise : F CFA, montants entiers.

---

## §0. Spécification

### §0.1 Endpoint

`POST /api/v2/devis` (et `GET`, même méthode de contrôleur — CLAUDE.md règle 2). **Public, sans jeton** : c'est un calcul pur sur des tarifs déjà publics (`getParameters` les expose déjà), sans identité ni effet de bord. Limité à `throttle:60,1`.

Paramètres :

| Champ | Règle | Note |
|---|---|---|
| `service` | `required`, `in:clando,livraison,coursier` | constantes `Tarif::CLANDO/LIVRAISON/COURSIER` |
| `distance_km` | `required`, `numeric`, `gt:0`, `max:500` | distance **routière** (le client l'obtient d'OSRM comme aujourd'hui) |
| `type` | `nullable`, `in:classic,vip` | `vip` n'a d'effet que pour `clando` |
| `lat_depart`, `lon_depart`, `lat_arrivee`, `lon_arrivee` | `nullable`, `numeric`, fournis **tous les quatre ou aucun** | si présents, `distance_km` doit être ≥ 95 % de la distance à vol d'oiseau (`App\Support\Distance::metres`) — une route ne peut pas être plus courte que la ligne droite ; sinon 422 |

Réponse 200 :

```json
{
  "response": 200,
  "data": {
    "service": "clando",
    "distance_km": 4.2,
    "type": "vip",
    "prix": 1600,
    "prix_classique": 1050,
    "devise": "XAF",
    "source": "grille",
    "tarif": { "prix_km": 250, "prix_min": 500, "prix_max": null, "majoration_vip": 50, "debut": "06:00", "fin": "22:00" },
    "calcule_a": "2026-09-03T10:15:00+01:00"
  }
}
```

Erreur de validation : 422, forme Laravel standard (`message` + `errors`). `source` vaut `grille` (plage horaire d'une grille `tarifs`), `parameters` (ligne plate active) ou `defaut` (ni l'un ni l'autre : valeurs historiques du client).

### §0.2 Formules de repli (sans grille) — copie exacte des clients

Notation : `ceil50(x) = ceil(x / 50) * 50`. `P` = ligne `parameters` active, valeurs par défaut entre parenthèses = celles codées en dur dans `plouletafcapp` quand `getParameters` n'a pas répondu.

- **clando** (`lib/screens/clando/clando.dart:2291-2308`) : `classique = ceil50(max(km × P.clando_kilometer (250), P.min_price_clando (500)))` ; `vip = ceil50(classique + classique × P.vip_percentage (50) / 100)`.
- **livraison** (`lib/screens/cart_screen/cart_screen.dart:298-308`) : `prix = ceil50(max(km × P.command_kilometer (63), P.min_price_command (400)))`.
- **coursier** (`lib/screens/clando/coursier_request_screen.dart:152-176`) : `prix = ceil50(P.min_price_coursier (500) + km × P.coursier_kilometer (200))` — formule **additive**, pas un plancher.

### §0.3 Avec grille

`TarifPlage::prixPour($km, $vip)` de la plage courante (déjà arrondi au 50 supérieur, VIP par `majoration_vip`, plancher/plafond). Le `prix_classique` renvoyé est `prixPour($km, false)`.

### §0.4 Recalcul à la création

- `ClandoController::Insertclando` : si `distance` est numérique et > 0, `price` = `Tarification::devis('clando', distance, type === 'vip')->prix` ; le `price` du client est ignoré (journalisé en `warning` s'il diffère, pour repérer les vieux builds). Sinon comportement inchangé (`price` client, plancher 1).
- `CoursierController::storeDeliveryOrder` : nouveau paramètre optionnel `distance_km` (`nullable|numeric|gt:0|max:500`) ; s'il est présent, `price` = devis coursier serveur ; sinon inchangé. `price` reste `required` pour ne pas casser les builds actuels.
- La réponse des deux endpoints n'est pas modifiée en forme ; `Insertclando` renvoie déjà le `price` enregistré (le client doit le lire, tâche côté app).

### §0.5 Hors périmètre (tranches suivantes, notées dans ARCHITECTURE.md)

- `OrderController::CreateOrder` & co (3 chemins de création, `delivery_fees` client) — même principe, mais `order_details` n'est pas testable localement et le flux a 3 variantes ; l'app cliente affichera déjà le frais serveur via `/v2/devis`.
- `RecalculDistanceDetours` (recalcule `base_price` sans arrondi ni VIP, depuis `parameters` seulement) — le rapprocher du moteur changerait des prix en cours de course, décision métier à part.
- Config distante / version minimale (niveau 2 de l'architecture server-driven).

---

## Fichiers

- Créer `app/Support/Devis.php` — objet valeur immuable (résultat d'un calcul).
- Créer `app/Support/Tarification.php` — moteur : `devis()` et `prixRetenu()`.
- Modifier `app/Support/GrilleTarifaire.php:46` — `parametres()` passe de `private` à `public` (le moteur en a besoin pour le repli).
- Créer `app/Http/Controllers/API/DevisController.php`.
- Modifier `routes/api.php` — nouveau groupe `prefix => 'v2'` après la fermeture du groupe `v1.0` (ligne 550).
- Modifier `app/Http/Controllers/API/ClandoController.php:19-35,58-83` (`Insertclando`).
- Modifier `app/Http/Controllers/API/CoursierController.php:31-72` (`storeDeliveryOrder`).
- Créer `tests/Unit/TarificationTest.php`, `tests/Feature/DevisApiTest.php`, `tests/Unit/PrixRetenuTest.php`.
- Modifier `C:\dev\ARCHITECTURE.md` (nouvelle §18), `C:\dev\CLAUDE.md` (règle 23), `C:\dev\TASKS.md`.

---

### Task 1 : Moteur `Tarification` + objet `Devis` (logique pure, tests unitaires)

**Files:**
- Create: `app/Support/Devis.php`
- Create: `app/Support/Tarification.php`
- Modify: `app/Support/GrilleTarifaire.php:46`
- Test: `tests/Unit/TarificationTest.php`

**Interfaces:**
- Consumes : `GrilleTarifaire::plage(string $service): ?TarifPlage`, `GrilleTarifaire::parametres(): ?Parameter` (rendu public ici), `TarifPlage::prixPour(float, bool): int`, `Tarif::CLANDO|LIVRAISON|COURSIER`.
- Produces : `Tarification::devis(string $service, float $distanceKm, bool $vip = false): Devis` ; `Devis` avec propriétés publiques en lecture `service, distanceKm, vip, prix, prixClassique, source, tarif (array), calculeA (Carbon)` et `toArray(): array`.

- [ ] **Step 1 : Écrire le test unitaire (échec attendu)**

```php
<?php
// tests/Unit/TarificationTest.php

namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use App\Support\Devis;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Tests\TestCase;

/**
 * Le moteur est testé sans base : les grilles et paramètres sont des modèles
 * non persistés injectés via une doublure de GrilleTarifaire. La base locale
 * a 51 migrations en attente (pas de table `tarifs`), et surtout ces
 * formules doivent être vérifiables sans dépendre de la ligne `parameters`
 * qui traîne en base.
 */
class TarificationTest extends TestCase
{
    /** Doublure : renvoie la plage et les paramètres donnés, sans requête. */
    private function grille(?TarifPlage $plage, ?Parameter $parametres): GrilleTarifaire
    {
        return new class($plage, $parametres) extends GrilleTarifaire {
            public function __construct(private ?TarifPlage $p, private ?Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return $this->p; }
            public function parametres(): ?Parameter { return $this->q; }
        };
    }

    private function parametres(array $valeurs = []): Parameter
    {
        return new Parameter($valeurs + [
            'clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50,
            'command_kilometer' => 63, 'min_price_command' => 400,
            'coursier_kilometer' => 200, 'min_price_coursier' => 500,
        ]);
    }

    // --- Repli sur `parameters` : copie exacte des formules de l'app cliente ---

    public function test_clando_sans_grille_applique_le_plancher_et_l_arrondi_au_50(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 1 km × 250 = 250 < 500 → plancher 500
        $this->assertSame(500, $moteur->devis(Tarif::CLANDO, 1.0)->prix);
        // 4.2 km × 250 = 1050 → déjà multiple de 50
        $this->assertSame(1050, $moteur->devis(Tarif::CLANDO, 4.2)->prix);
        // 4.21 km × 250 = 1052,5 → 1100
        $this->assertSame(1100, $moteur->devis(Tarif::CLANDO, 4.21)->prix);
    }

    public function test_clando_vip_sans_grille_majore_le_prix_classique_deja_arrondi(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        $devis = $moteur->devis(Tarif::CLANDO, 4.2, vip: true);

        // classique 1050 ; vip = ceil50(1050 + 1050 × 50 %) = ceil50(1575) = 1600
        $this->assertSame(1050, $devis->prixClassique);
        $this->assertSame(1600, $devis->prix);
        $this->assertTrue($devis->vip);
        $this->assertSame('parameters', $devis->source);
    }

    public function test_livraison_sans_grille_reproduit_le_calcul_du_panier(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 3 km × 63 = 189 < 400 → 400 ; 10 km × 63 = 630 → 650
        $this->assertSame(400, $moteur->devis(Tarif::LIVRAISON, 3.0)->prix);
        $this->assertSame(650, $moteur->devis(Tarif::LIVRAISON, 10.0)->prix);
    }

    public function test_coursier_sans_grille_est_additif_pas_un_plancher(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 500 + 3 km × 200 = 1100 (et non max(600, 500))
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
        // 500 + 2.3 × 200 = 960 → 1000
        $this->assertSame(1000, $moteur->devis(Tarif::COURSIER, 2.3)->prix);
    }

    public function test_le_vip_est_ignore_hors_clando(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0, vip: true)->prix);
        $this->assertFalse($moteur->devis(Tarif::COURSIER, 3.0, vip: true)->vip);
    }

    public function test_sans_parameters_les_valeurs_historiques_du_client_s_appliquent(): void
    {
        $moteur = new Tarification($this->grille(null, null));

        $devis = $moteur->devis(Tarif::CLANDO, 4.2);

        $this->assertSame(1050, $devis->prix);
        $this->assertSame('defaut', $devis->source);
        $this->assertSame(650, $moteur->devis(Tarif::LIVRAISON, 10.0)->prix);
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
    }

    public function test_une_ligne_parameters_sans_tarif_coursier_retombe_sur_l_historique(): void
    {
        $sansCoursier = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500]);
        $moteur = new Tarification($this->grille(null, $sansCoursier));

        // coursier_kilometer / min_price_coursier absents → 500 + 200/km
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
    }

    // --- Avec grille : TarifPlage décide ---

    public function test_avec_grille_c_est_la_plage_qui_calcule(): void
    {
        $plage = new TarifPlage([
            'debut' => '00:00', 'fin' => '00:00',
            'prix_km' => 300, 'prix_min' => 700, 'prix_max' => 5000,
            'commission' => 20, 'majoration_vip' => 25, 'ordre' => 0,
        ]);
        $moteur = new Tarification($this->grille($plage, $this->parametres()));

        $devis = $moteur->devis(Tarif::CLANDO, 4.0, vip: true);

        // 4 × 300 = 1200 ; +25 % = 1500 ; classique 1200
        $this->assertSame(1500, $devis->prix);
        $this->assertSame(1200, $devis->prixClassique);
        $this->assertSame('grille', $devis->source);
        $this->assertSame(300, $devis->tarif['prix_km']);
        $this->assertSame('00:00', $devis->tarif['debut']);
        // 30 km × 300 = 9000 → plafond 5000
        $this->assertSame(5000, $moteur->devis(Tarif::CLANDO, 30.0)->prix);
    }

    public function test_un_service_inconnu_est_refuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Tarification($this->grille(null, null)))->devis('colis', 1.0);
    }

    public function test_to_array_expose_la_forme_attendue_par_les_applications(): void
    {
        $devis = (new Tarification($this->grille(null, $this->parametres())))->devis(Tarif::CLANDO, 4.2, vip: true);

        $tableau = $devis->toArray();

        $this->assertSame(
            ['service', 'distance_km', 'type', 'prix', 'prix_classique', 'devise', 'source', 'tarif', 'calcule_a'],
            array_keys($tableau)
        );
        $this->assertSame('vip', $tableau['type']);
        $this->assertSame('XAF', $tableau['devise']);
        $this->assertInstanceOf(Devis::class, $devis);
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run : `php artisan test --filter TarificationTest`
Attendu : erreur `Class "App\Support\Tarification" not found`.

- [ ] **Step 3 : Rendre `GrilleTarifaire::parametres()` public**

Dans `app/Support/GrilleTarifaire.php`, remplacer :

```php
    private function parametres(): ?Parameter
    {
        return $this->parametres ??= Parameter::active();
    }
```

par :

```php
    /**
     * La ligne plate `parameters` active, ou null. Publique pour que le
     * moteur de tarification (App\Support\Tarification) puisse appliquer le
     * même repli que les commissions ci-dessous — sans dupliquer la lecture.
     */
    public function parametres(): ?Parameter
    {
        return $this->parametres ??= Parameter::active();
    }
```

- [ ] **Step 4 : Créer `app/Support/Devis.php`**

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Le résultat d'un calcul de prix : ce que le serveur s'engage à facturer
 * pour un service et une distance, à l'instant du calcul.
 *
 * Immuable et sans logique : la logique est dans Tarification, et ce qui
 * sort d'ici est directement sérialisé vers les applications mobiles.
 */
final class Devis
{
    public const SOURCE_GRILLE = 'grille';
    public const SOURCE_PARAMETERS = 'parameters';
    public const SOURCE_DEFAUT = 'defaut';

    public function __construct(
        public readonly string $service,
        public readonly float $distanceKm,
        public readonly bool $vip,
        public readonly int $prix,
        public readonly int $prixClassique,
        public readonly string $source,
        /** @var array<string, mixed> Le tarif appliqué, pour affichage/débogage. */
        public readonly array $tarif,
        public readonly CarbonImmutable $calculeA,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'distance_km' => $this->distanceKm,
            'type' => $this->vip ? 'vip' : 'classic',
            'prix' => $this->prix,
            'prix_classique' => $this->prixClassique,
            'devise' => 'XAF',
            'source' => $this->source,
            'tarif' => $this->tarif,
            'calcule_a' => $this->calculeA->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5 : Créer `app/Support/Tarification.php`**

```php
<?php

namespace App\Support;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use Carbon\CarbonImmutable;

/**
 * Le prix d'une course, calculé par le serveur.
 *
 * Jusqu'ici chaque application mobile calculait le prix elle-même à partir
 * de `getParameters`, puis l'envoyait au serveur qui l'enregistrait tel quel
 * — un montant modifié côté téléphone servait ensuite de base à la commission
 * de l'agent. Ce moteur est désormais la seule source : les applications
 * affichent ce qu'il renvoie (`POST /api/v2/devis`) et les créations de
 * course le rappellent (voir prixRetenu()).
 *
 * Deux générations de tarifs, comme pour les commissions (GrilleTarifaire) :
 *  - une grille par service et plage horaire → TarifPlage::prixPour() ;
 *  - sinon la ligne plate `parameters`, avec **les formules exactes des
 *    applications** (voir le plan 2026-09-03-moteur-tarification-devis §0.2)
 *    pour qu'aucun prix affiché ne bouge le jour de la bascule.
 */
class Tarification
{
    /**
     * Valeurs codées en dur dans plouletafcapp quand getParameters n'a pas
     * répondu. Reprises telles quelles : sans `parameters`, le serveur doit
     * donner le même prix que l'application aurait affiché.
     */
    private const DEFAUTS = [
        Tarif::CLANDO => ['km' => 250, 'min' => 500, 'vip' => 50],
        Tarif::LIVRAISON => ['km' => 63, 'min' => 400],
        Tarif::COURSIER => ['km' => 200, 'min' => 500],
    ];

    public function __construct(private GrilleTarifaire $grille)
    {
    }

    public function devis(string $service, float $distanceKm, bool $vip = false): Devis
    {
        if (! array_key_exists($service, Tarif::SERVICES)) {
            throw new \InvalidArgumentException("Service inconnu : {$service}");
        }

        // Le VIP n'existe que pour le clando (Tarif::SERVICES_AVEC_VIP) ; le
        // demander ailleurs n'est pas une erreur, c'est simplement sans effet.
        $vip = $vip && in_array($service, Tarif::SERVICES_AVEC_VIP, true);
        $distanceKm = max($distanceKm, 0.0);

        $plage = $this->grille->plage($service);

        if ($plage) {
            return $this->depuisLaGrille($service, $distanceKm, $vip, $plage);
        }

        return $this->depuisLesParametres($service, $distanceKm, $vip, $this->grille->parametres());
    }

    private function depuisLaGrille(string $service, float $km, bool $vip, TarifPlage $plage): Devis
    {
        return new Devis(
            service: $service,
            distanceKm: $km,
            vip: $vip,
            prix: $plage->prixPour($km, $vip),
            prixClassique: $plage->prixPour($km, false),
            source: Devis::SOURCE_GRILLE,
            tarif: [
                'prix_km' => $plage->prix_km,
                'prix_min' => $plage->prix_min,
                'prix_max' => $plage->prix_max,
                'majoration_vip' => $plage->majoration_vip,
                'debut' => $plage->debutCourt(),
                'fin' => $plage->finCourte(),
            ],
            calculeA: CarbonImmutable::now(),
        );
    }

    private function depuisLesParametres(string $service, float $km, bool $vip, ?Parameter $p): Devis
    {
        $defauts = self::DEFAUTS[$service];

        // Une ligne `parameters` peut exister sans les colonnes coursier
        // (grilles enregistrées avant leur ajout) : champ par champ, on
        // retombe sur la valeur historique plutôt que sur 0.
        $valeur = fn (?string $colonne, int $defaut): float => (float) ($colonne !== null && $p?->getAttribute($colonne) !== null
            ? $p->getAttribute($colonne)
            : $defaut);

        [$prixKm, $prixMin, $majorationVip] = match ($service) {
            Tarif::CLANDO => [
                $valeur('clando_kilometer', $defauts['km']),
                $valeur('min_price_clando', $defauts['min']),
                $valeur('vip_percentage', $defauts['vip']),
            ],
            Tarif::LIVRAISON => [
                $valeur('command_kilometer', $defauts['km']),
                $valeur('min_price_command', $defauts['min']),
                0.0,
            ],
            Tarif::COURSIER => [
                $valeur('coursier_kilometer', $defauts['km']),
                $valeur('min_price_coursier', $defauts['min']),
                0.0,
            ],
        };

        // Coursier : formule additive (base + km × tarif), les deux autres :
        // plancher. C'est ce que font les écrans respectifs de l'application.
        $classique = $service === Tarif::COURSIER
            ? self::arrondi50($prixMin + $km * $prixKm)
            : self::arrondi50(max($km * $prixKm, $prixMin));

        // VIP : majoration appliquée au prix classique *déjà arrondi*, puis
        // nouvel arrondi — l'ordre exact de clando.dart::_calculateVipPrice.
        $prix = $vip ? self::arrondi50($classique + $classique * $majorationVip / 100) : $classique;

        return new Devis(
            service: $service,
            distanceKm: $km,
            vip: $vip,
            prix: $prix,
            prixClassique: $classique,
            source: $p ? Devis::SOURCE_PARAMETERS : Devis::SOURCE_DEFAUT,
            tarif: [
                'prix_km' => (int) $prixKm,
                'prix_min' => (int) $prixMin,
                'prix_max' => null,
                'majoration_vip' => $vip || $service === Tarif::CLANDO ? $majorationVip : null,
                'debut' => null,
                'fin' => null,
            ],
            calculeA: CarbonImmutable::now(),
        );
    }

    /** Arrondi au multiple de 50 supérieur — aucun prix n'a d'unité sous 50 F. */
    public static function arrondi50(float $montant): int
    {
        return (int) (ceil($montant / 50) * 50);
    }
}
```

- [ ] **Step 6 : Lancer, vérifier le succès**

Run : `php artisan test --filter TarificationTest`
Attendu : 10 tests verts.

- [ ] **Step 7 : Vérifier l'absence de régression sur les commissions**

Run : `php artisan test --filter "GrilleTarifaireTest|ApiAuthentificationTest"`
Attendu : `ApiAuthentificationTest` vert ; `GrilleTarifaireTest` échoue **déjà avant ce plan** (table `tarifs` absente localement — constaté le 2026-09-03) : vérifier que l'erreur est bien `Base table or view not found: tarifs` et rien d'autre.

- [ ] **Step 8 : `git status --short --branch`** — attendu : `A/??` sur les 3 nouveaux fichiers, ` M app/Support/GrilleTarifaire.php`, plus ` M routes/api.php` préexistant (throttle OTP). Pas de commit.

---

### Task 2 : Endpoint `POST /api/v2/devis`

**Files:**
- Create: `app/Http/Controllers/API/DevisController.php`
- Modify: `routes/api.php:550` (après la fermeture du groupe `v1.0`)
- Test: `tests/Feature/DevisApiTest.php`

**Interfaces:**
- Consumes : `Tarification::devis(string, float, bool): Devis`, `Devis::toArray()`, `Distance::metres(float,float,float,float): float`.
- Produces : route nommée `v2.devis` (GET+POST `/api/v2/devis`), réponse `{response: 200, data: Devis::toArray()}`.

- [ ] **Step 1 : Écrire le test (échec attendu)**

```php
<?php
// tests/Feature/DevisApiTest.php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use Tests\TestCase;

/**
 * L'endpoint de devis, avec une GrilleTarifaire doublée dans le conteneur :
 * aucune table `tarifs` n'existe sur la base locale, et le test doit de toute
 * façon être insensible à la ligne `parameters` réellement active.
 */
class DevisApiTest extends TestCase
{
    private function avecGrille(?TarifPlage $plage, ?Parameter $parametres): void
    {
        $this->app->instance(GrilleTarifaire::class, new class($plage, $parametres) extends GrilleTarifaire {
            public function __construct(private ?TarifPlage $p, private ?Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return $this->p; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    private function parametres(): Parameter
    {
        return new Parameter([
            'clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50,
            'command_kilometer' => 63, 'min_price_command' => 400,
            'coursier_kilometer' => 200, 'min_price_coursier' => 500,
        ]);
    }

    public function test_un_devis_clando_vip_est_calcule_par_le_serveur(): void
    {
        $this->avecGrille(null, $this->parametres());

        $reponse = $this->postJson('/api/v2/devis', [
            'service' => 'clando', 'distance_km' => 4.2, 'type' => 'vip',
        ]);

        $reponse->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.prix', 1600)
            ->assertJsonPath('data.prix_classique', 1050)
            ->assertJsonPath('data.type', 'vip')
            ->assertJsonPath('data.source', 'parameters')
            ->assertJsonPath('data.devise', 'XAF');
    }

    public function test_get_et_post_donnent_la_meme_reponse(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->getJson('/api/v2/devis?service=coursier&distance_km=3')
            ->assertOk()->assertJsonPath('data.prix', 1100);
        $this->postJson('/api/v2/devis', ['service' => 'coursier', 'distance_km' => 3])
            ->assertOk()->assertJsonPath('data.prix', 1100);
    }

    public function test_la_grille_l_emporte_quand_elle_existe(): void
    {
        $this->avecGrille(new TarifPlage([
            'debut' => '00:00', 'fin' => '00:00', 'prix_km' => 300, 'prix_min' => 700,
            'prix_max' => null, 'commission' => 20, 'ordre' => 0,
        ]), $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'livraison', 'distance_km' => 10])
            ->assertOk()
            ->assertJsonPath('data.prix', 3000)
            ->assertJsonPath('data.source', 'grille')
            ->assertJsonPath('data.tarif.prix_km', 300);
    }

    public function test_service_inconnu_ou_distance_nulle_sont_refuses(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'colis', 'distance_km' => 3])
            ->assertStatus(422)->assertJsonValidationErrors(['service']);
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);
        $this->postJson('/api/v2/devis', ['service' => 'clando'])
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 2, 'type' => 'premium'])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_une_distance_plus_courte_que_le_vol_d_oiseau_est_refusee(): void
    {
        $this->avecGrille(null, $this->parametres());

        // Douala centre → Bonabéri ≈ 5 km à vol d'oiseau : 1 km de route est impossible.
        $coords = ['lat_depart' => 4.0511, 'lon_depart' => 9.7679, 'lat_arrivee' => 4.0725, 'lon_arrivee' => 9.6906];

        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 1] + $coords)
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);

        // 9 km de route pour 8,7 km à vol d'oiseau : cohérent.
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 9] + $coords)
            ->assertOk();
    }

    public function test_les_coordonnees_vont_par_quatre(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 2, 'lat_depart' => 4.05])
            ->assertStatus(422)->assertJsonValidationErrors(['lon_depart', 'lat_arrivee', 'lon_arrivee']);
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run : `php artisan test --filter DevisApiTest`
Attendu : 404 sur `/api/v2/devis` (route inexistante) → assertions en échec.

- [ ] **Step 3 : Créer le contrôleur**

```php
<?php
// app/Http/Controllers/API/DevisController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tarif;
use App\Support\Distance;
use App\Support\Tarification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Devis d'une course : le prix que le serveur facturera pour un service et
 * une distance, à l'instant de l'appel.
 *
 * Première route du préfixe `v2`. Volontairement **sans jeton** : c'est un
 * calcul pur sur des tarifs déjà publics (`getParameters` les expose), sans
 * identité ni effet de bord — le recalcul qui fait foi a lieu à la création
 * de la course (Insertclando, storeDeliveryOrder). Limité par throttle dans
 * routes/api.php.
 */
class DevisController extends Controller
{
    /** Une route ne peut pas être plus courte que la ligne droite ; 5 % de marge pour les arrondis GPS. */
    private const TOLERANCE_VOL_D_OISEAU = 0.95;

    public function __invoke(Request $request, Tarification $tarification): JsonResponse
    {
        $valide = $request->validate([
            'service' => ['required', 'in:' . implode(',', array_keys(Tarif::SERVICES))],
            'distance_km' => ['required', 'numeric', 'gt:0', 'max:500'],
            'type' => ['nullable', 'in:classic,vip'],
            'lat_depart' => ['nullable', 'numeric', 'required_with:lon_depart,lat_arrivee,lon_arrivee'],
            'lon_depart' => ['nullable', 'numeric', 'required_with:lat_depart,lat_arrivee,lon_arrivee'],
            'lat_arrivee' => ['nullable', 'numeric', 'required_with:lat_depart,lon_depart,lon_arrivee'],
            'lon_arrivee' => ['nullable', 'numeric', 'required_with:lat_depart,lon_depart,lat_arrivee'],
        ]);

        $distanceKm = (float) $valide['distance_km'];

        if (isset($valide['lat_depart'], $valide['lon_depart'], $valide['lat_arrivee'], $valide['lon_arrivee'])) {
            $volDOiseauKm = Distance::metres(
                (float) $valide['lat_depart'], (float) $valide['lon_depart'],
                (float) $valide['lat_arrivee'], (float) $valide['lon_arrivee'],
            ) / 1000;

            if ($distanceKm < $volDOiseauKm * self::TOLERANCE_VOL_D_OISEAU) {
                throw ValidationException::withMessages([
                    'distance_km' => 'Distance incohérente avec les coordonnées fournies.',
                ]);
            }
        }

        $devis = $tarification->devis(
            $valide['service'],
            $distanceKm,
            ($valide['type'] ?? 'classic') === 'vip',
        );

        return response()->json(['response' => 200, 'data' => $devis->toArray()]);
    }
}
```

- [ ] **Step 4 : Déclarer la route**

À la fin de `routes/api.php`, après le `});` qui ferme le groupe `v1.0` (ligne 550), ajouter :

```php

/*
|--------------------------------------------------------------------------
| API v2
|--------------------------------------------------------------------------
|
| Nouveau préfixe, distinct de v1.0 dont aucune route n'est authentifiée par
| défaut (CLAUDE.md règle 8). Ici chaque route dit explicitement ce qu'elle
| exige : soit App\Support\ApiAuthentification::utilisateurOuErreur(), soit
| un commentaire expliquant pourquoi elle s'en passe. GET et POST pointent
| toujours vers la même méthode (règle 2).
*/
Route::group(['namespace' => 'App\Http\Controllers\API', 'prefix' => 'v2'], function () {
    // Public : calcul pur sur des tarifs déjà exposés par getParameters (voir DevisController).
    Route::get('devis', 'DevisController')->middleware('throttle:60,1')->name('v2.devis.get');
    Route::post('devis', 'DevisController')->middleware('throttle:60,1')->name('v2.devis.post');
});
```

- [ ] **Step 5 : Lancer, vérifier le succès**

Run : `php artisan test --filter "DevisApiTest|TarificationTest"`
Attendu : 16 tests verts. Puis `php artisan route:list --path=v2` doit lister `GET|HEAD api/v2/devis` et `POST api/v2/devis`.

- [ ] **Step 6 : `git status --short --branch`** — `?? app/Http/Controllers/API/DevisController.php`, `?? tests/Feature/DevisApiTest.php`, ` M routes/api.php`. Pas de commit.

---

### Task 3 : `Insertclando` recalcule le prix côté serveur

**Files:**
- Modify: `app/Support/Tarification.php` (ajout de `prixRetenu()`)
- Modify: `app/Http/Controllers/API/ClandoController.php:19-35` et `:58-83`
- Test: `tests/Unit/PrixRetenuTest.php`

**Interfaces:**
- Produces : `Tarification::prixRetenu(string $service, mixed $prixClient, mixed $distanceKm, bool $vip = false): ?int` — `null` = prix client invalide et pas de distance exploitable (l'appelant répond 400 comme aujourd'hui) ; sinon l'entier à enregistrer. Journalise en `warning` (`Log::warning('Tarification: prix client différent du prix serveur', [...])`) quand les deux existent et diffèrent.

- [ ] **Step 1 : Écrire le test (échec attendu)**

```php
<?php
// tests/Unit/PrixRetenuTest.php

namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Quel prix enregistrer à la création d'une course : celui que le client a
 * envoyé, ou celui que le serveur calcule ? Testé comme fonction pure parce
 * que la table `clando` n'existe pas sur la base locale (51 migrations en
 * attente) — Insertclando lui-même n'est donc pas testable ici.
 */
class PrixRetenuTest extends TestCase
{
    private function moteur(): Tarification
    {
        $parametres = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50]);

        return new Tarification(new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    public function test_avec_une_distance_le_prix_serveur_l_emporte_sur_le_prix_client(): void
    {
        Log::shouldReceive('warning')->once();

        // Le client annonce 100 F pour 4,2 km : le serveur retient 1050.
        $this->assertSame(1050, $this->moteur()->prixRetenu(Tarif::CLANDO, '100', '4.2'));
    }

    public function test_un_prix_client_egal_au_prix_serveur_ne_journalise_rien(): void
    {
        Log::shouldReceive('warning')->never();

        $this->assertSame(1050, $this->moteur()->prixRetenu(Tarif::CLANDO, 1050, 4.2));
    }

    public function test_sans_distance_le_prix_client_est_conserve_comme_avant(): void
    {
        Log::shouldReceive('warning')->never();

        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, '800', null));
        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, 800, 0));
        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, 800, 'abc'));
    }

    public function test_sans_distance_un_prix_client_invalide_donne_null(): void
    {
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, '0', null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, 'abc', null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, null, null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, -5, null));
    }

    public function test_avec_une_distance_un_prix_client_absent_n_empeche_rien(): void
    {
        Log::shouldReceive('warning')->never();

        // Un futur client n'enverra plus de prix du tout.
        $this->assertSame(1600, $this->moteur()->prixRetenu(Tarif::CLANDO, null, 4.2, vip: true));
    }
}
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run : `php artisan test --filter PrixRetenuTest`
Attendu : `Call to undefined method App\Support\Tarification::prixRetenu()`.

- [ ] **Step 3 : Ajouter `prixRetenu()` à `Tarification`**

Dans `app/Support/Tarification.php`, ajouter `use Illuminate\Support\Facades\Log;` en tête, puis, après `devis()` :

```php
    /**
     * Le prix à enregistrer à la création d'une course.
     *
     * Dès que le client fournit une distance exploitable, le serveur calcule
     * et son prix l'emporte — le montant envoyé par le téléphone n'est plus
     * qu'un indice, journalisé s'il diffère (pour repérer les anciens builds
     * ou une manipulation). Sans distance, on garde le comportement
     * historique : prix client, plancher à 1 F, null si invalide.
     */
    public function prixRetenu(string $service, mixed $prixClient, mixed $distanceKm, bool $vip = false): ?int
    {
        $prixClientValide = is_numeric($prixClient) && (float) $prixClient >= 1 ? (int) round((float) $prixClient) : null;

        if (! is_numeric($distanceKm) || (float) $distanceKm <= 0) {
            return $prixClientValide;
        }

        $prixServeur = $this->devis($service, (float) $distanceKm, $vip)->prix;

        if ($prixClientValide !== null && $prixClientValide !== $prixServeur) {
            Log::warning('Tarification: prix client différent du prix serveur', [
                'service' => $service,
                'distance_km' => (float) $distanceKm,
                'vip' => $vip,
                'prix_client' => $prixClientValide,
                'prix_serveur' => $prixServeur,
            ]);
        }

        return $prixServeur;
    }
```

- [ ] **Step 4 : Lancer, vérifier le succès**

Run : `php artisan test --filter PrixRetenuTest`
Attendu : 5 tests verts.

- [ ] **Step 5 : Brancher `Insertclando`**

Dans `app/Http/Controllers/API/ClandoController.php`, remplacer le bloc lignes 21-35 :

```php
        /*
         | Le prix vient entièrement du téléphone du client, sans aucune
         | validation jusqu'ici — un montant nul, négatif ou non numérique
         | était accepté tel quel et servait ensuite de base à la commission
         | de l'agent et au calcul du solde. Un plancher à 1 FCFA n'empêche
         | aucun tarif légitime (aucune course ne coûte 0), seulement les cas
         | manifestement invalides.
         */
        if (! is_numeric($request->price) || (float) $request->price < 1) {
            return response()->json([
                'response' => 400,
                'message' => 'Prix de course invalide.',
            ]);
        }
```

par :

```php
        /*
         | Le prix est désormais calculé par le serveur dès que le client
         | envoie la distance (App\Support\Tarification::prixRetenu) : le
         | montant envoyé par le téléphone ne fait plus foi — il servait
         | jusqu'ici de base à la commission de l'agent et au solde sans
         | aucune vérification. Sans distance (anciens builds), on garde le
         | comportement précédent : prix client, plancher à 1 F.
         */
        $prix = app(\App\Support\Tarification::class)->prixRetenu(
            \App\Models\Tarif::CLANDO,
            $request->price,
            $request->distance,
            $request->input('type') === 'vip'
        );

        if ($prix === null) {
            return response()->json([
                'response' => 400,
                'message' => 'Prix de course invalide.',
            ]);
        }
```

Puis, plus bas dans la même méthode, remplacer `(float) $request->price,` (argument de `commissionClando`, ligne ~66) par `(float) $prix,` et `'price'=>$request->price,` (ligne ~83) par `'price'=>$prix,`. Vérifier avec `grep -n "request->price" app/Http/Controllers/API/ClandoController.php` qu'il ne reste **aucune** occurrence dans `Insertclando` (les autres méthodes ne sont pas concernées).

- [ ] **Step 6 : Vérification statique**

Run : `php -l app/Http/Controllers/API/ClandoController.php` puis `php artisan test --filter "PrixRetenuTest|TarificationTest|DevisApiTest"`
Attendu : `No syntax errors`, 21 tests verts.

- [ ] **Step 7 : `git status --short --branch`** — ` M app/Http/Controllers/API/ClandoController.php`, ` M app/Support/Tarification.php`, `?? tests/Unit/PrixRetenuTest.php`. Pas de commit.

---

### Task 4 : `storeDeliveryOrder` accepte `distance_km` et recalcule

**Files:**
- Modify: `app/Http/Controllers/API/CoursierController.php:31-72`

**Interfaces:**
- Consumes : `Tarification::prixRetenu(Tarif::COURSIER, $prixClient, $distanceKm)`.
- Produces : paramètre optionnel `distance_km` sur `POST v1.0/storeDeliveryOrder` (`nullable|numeric|gt:0|max:500`). Forme de réponse inchangée.

- [ ] **Step 1 : Ajouter la règle de validation**

Dans `storeDeliveryOrder`, après la ligne `'price' => ['required', 'numeric', 'min:1'],` ajouter :

```php
            // Distance routière du colis (km). Optionnelle pour ne pas casser
            // les builds déjà installés ; dès qu'elle est là, le serveur
            // recalcule le prix et ignore celui du client.
            'distance_km' => ['nullable', 'numeric', 'gt:0', 'max:500'],
```

- [ ] **Step 2 : Recalculer avant l'insertion**

Juste après le bloc `$request->validate([...]);` (avant la boucle `do { $ref = ... }`), ajouter :

```php
        // Voir App\Support\Tarification::prixRetenu : prix serveur si distance
        // fournie, prix client (déjà validé ≥ 1 ci-dessus) sinon.
        $prix = app(\App\Support\Tarification::class)->prixRetenu(
            \App\Models\Tarif::COURSIER,
            $valide['price'],
            $valide['distance_km'] ?? null,
        );
```

puis remplacer `'price' => (float) $valide['price'],` par `'price' => (float) $prix,`.

- [ ] **Step 3 : Vérification**

Run : `php -l app/Http/Controllers/API/CoursierController.php && php artisan test --filter "CoursierTest|ClandoCoursierTest"`
Attendu : `No syntax errors`. Si `CoursierTest`/`ClandoCoursierTest` échouent, vérifier que c'est la même erreur qu'**avant** la modification (`git stash` → relancer → `git stash pop`) : la base locale n'a pas les colonnes récentes d'`order_details`, ces tests sont déjà rouges pour cette raison.

- [ ] **Step 4 : `git status --short --branch`** — ` M app/Http/Controllers/API/CoursierController.php`. Pas de commit.

---

### Task 5 : Documentation (ARCHITECTURE.md §18, CLAUDE.md règle 23, TASKS.md)

**Files:**
- Modify: `C:\dev\ARCHITECTURE.md` (ajouter §18 à la fin)
- Modify: `C:\dev\CLAUDE.md` (ajouter la règle 23 après la 22)
- Modify: `C:\dev\TASKS.md` (ligne « En cours » de ce chantier)

- [ ] **Step 1 : ARCHITECTURE.md §18**

Ajouter à la fin :

```markdown
## 18. Moteur de tarification côté serveur — `POST /api/v2/devis` (tranche 1 « server-driven », 2026-09-03)

**Problème fermé.** Chaque application calculait le prix d'une course elle-même à partir de `getParameters` (`clando.dart:2291-2308`, `cart_screen.dart:298-308`, `coursier_request_screen.dart:152-176`) puis l'envoyait au serveur, qui l'enregistrait tel quel — le montant servait ensuite de base à la commission de l'agent et au solde. Un prix modifié côté téléphone passait.

**Ce qui existe maintenant.**
- `App\Support\Tarification` (moteur pur) + `App\Support\Devis` (résultat immuable). `devis(service, km, vip)` : grille `tarifs`/`tarif_plages` via `GrilleTarifaire::plage()` → `TarifPlage::prixPour()` ; sinon ligne `parameters` avec **les formules exactes des écrans clients** (clando/livraison : `ceil50(max(km×tarif, min))`, VIP majoré après arrondi ; coursier : `ceil50(min + km×tarif)` — additif) ; sinon les valeurs historiques codées en dur dans l'app (250/500/50, 63/400, 200/500). `source` ∈ `grille|parameters|defaut`.
- `POST|GET /api/v2/devis` (`DevisController`, `throttle:60,1`, **public**, voir commentaire du contrôleur) : `service`, `distance_km`, `type`, coordonnées optionnelles (4 ou 0) → 422 si la distance est < 95 % du vol d'oiseau. Premier endpoint du préfixe `v2` : chaque route y documente son authentification (contrairement à `v1.0`).
- `Tarification::prixRetenu(service, prixClient, distanceKm, vip)` : prix serveur dès que le client envoie une distance > 0 (le prix client est journalisé en `warning` s'il diffère), sinon comportement historique. Branché dans `ClandoController::Insertclando` (`distance` existait déjà dans le payload) et `CoursierController::storeDeliveryOrder` (nouveau paramètre optionnel `distance_km`).

**Tests.** `tests/Unit/TarificationTest.php` (10), `tests/Unit/PrixRetenuTest.php` (5), `tests/Feature/DevisApiTest.php` (6) — tous avec une `GrilleTarifaire` doublée dans le conteneur : la base locale a 51 migrations en attente (pas de table `tarifs` ni `clando`), voir « Limites ».

**Limites / suite.**
- `OrderController::CreateOrder` & co (`delivery_fees` envoyés par le client, 3 chemins) : pas encore recalculés côté serveur — tranche suivante. `RecalculDistanceDetours` calcule encore `base_price` depuis `parameters` seuls, sans arrondi ni VIP.
- Tant que `plouletafcapp` n'envoie pas `distance_km` à `storeDeliveryOrder` et ne lit pas le `price` renvoyé par `insertclando`, le prix affiché à l'utilisateur peut différer du prix enregistré (le serveur a raison). Tâche côté app cliente : consommer `/v2/devis` pour l'affichage et supprimer les formules locales.
- La base de développement locale est en retard de 51 migrations : `GrilleTarifaireTest`, `CoursierTest` et les tests `Auth*` sur `clando` y sont rouges pour cette seule raison. Les tests de cette tranche ont été conçus pour ne pas en dépendre.
```

- [ ] **Step 2 : CLAUDE.md règle 23**

Après la règle 22, ajouter :

```markdown
23. **Le prix d'une course est calculé par le serveur (`App\Support\Tarification`, exposé par `POST /api/v2/devis`), jamais par une application.** Toute nouvelle formule de prix (nouveau service, promotion, majoration) s'ajoute dans `Tarification` — et dans `TarifPlage::prixPour()` si elle concerne les grilles — puis les applications l'affichent via `/v2/devis`. Ne jamais réintroduire un calcul de prix côté Flutter, ni faire confiance à un `price` reçu d'un client sur une route de création : passer par `Tarification::prixRetenu()`. Le préfixe `v2` de `routes/api.php` est le nouveau standard : chaque route y déclare explicitement son authentification (jeton via `ApiAuthentification::utilisateurOuErreur()`, ou commentaire justifiant son absence) — ne rien y ajouter « comme en v1.0 ». Voir `ARCHITECTURE.md` §18.
```

- [ ] **Step 3 : TASKS.md** — compléter la ligne « En cours » Claude Code du chantier avec « côté serveur terminé, N tests verts » et le décompte exact.

- [ ] **Step 4 : Vérification secrets** — `grep -rniE "password|secret|token=[A-Za-z0-9]" docs/superpowers/plans/2026-09-03-moteur-tarification-devis.md app/Support/Tarification.php app/Support/Devis.php app/Http/Controllers/API/DevisController.php tests/Unit/TarificationTest.php tests/Unit/PrixRetenuTest.php tests/Feature/DevisApiTest.php` doit ne rien renvoyer.

---

## Auto-revue

- **Couverture §0** : §0.1 → Task 2 ; §0.2/§0.3 → Task 1 ; §0.4 → Tasks 3 et 4 ; §0.5 → Task 5 (documenté comme hors périmètre).
- **Signatures** : `Tarification::devis(string, float, bool = false): Devis` (T1, utilisé T2/T3) ; `Tarification::prixRetenu(string, mixed, mixed, bool = false): ?int` (T3, utilisé T4) ; `GrilleTarifaire::parametres(): ?Parameter` public (T1, doublé dans T1/T2/T3) ; `Devis::toArray()` clés dans l'ordre `service, distance_km, type, prix, prix_classique, devise, source, tarif, calcule_a` (T1 test, T2 réponse).
- **Arithmétique des tests** vérifiée à la main : 4.2×250=1050 ; 1050×1.5=1575→1600 ; 4.21×250=1052.5→1100 ; 10×63=630→650 ; 500+3×200=1100 ; 500+2.3×200=960→1000 ; grille 4×300=1200, ×1.25=1500 ; 30×300=9000→plafond 5000 ; livraison grille 10×300=3000 ≥ 700.
- **Vol d'oiseau** (T2) : (4.0511, 9.7679) → (4.0725, 9.6906) : Δlat 0.0214° ≈ 2.38 km, Δlon 0.0773° × cos(4°) ≈ 8.57 km → ≈ 8.9 km. 1 km < 8.45 (95 %) → refusé ; 9 km ≥ 8.45 → accepté.
