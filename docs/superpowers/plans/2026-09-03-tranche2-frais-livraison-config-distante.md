# Tranche 2 « server-driven » — frais de livraison serveur, détours, configuration distante — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fermer les deux derniers points où le client dicte encore un prix (frais de livraison du panier, recalcul des détours), et donner aux deux apps une source unique de configuration (`/api/v2/config`) : version minimale, point de retrait, contact, fonctionnalités activables.

**Architecture:** Même schéma que la tranche 1 (`docs/superpowers/plans/2026-09-03-moteur-tarification-devis.md`) : une classe de support pure et testable par doublure (`FraisDeLivraison`), branchée aux deux chemins de création de commande (`creerDepuisPanier` via `validerPanier`, et l'ancien `CreateOrder`) ; `RecalculDistanceDetours` découpé en trois fonctions (points de passage, OSRM, application du tarif) pour être testé avec `Http::fake()` ; un `ConfigController` v2 qui agrège des sources déjà existantes (`config/mobile_app.php`, `PointDeLivraison`, `MobileAppService`). Côté apps, un `RemoteConfigService` (cache local, repli) consommé au démarrage.

**Tech Stack:** Laravel 10 / PHPUnit (`php artisan test`), Flutter (`http`, `MockClient`, `shared_preferences`), OSRM public.

**Spec:** §0 ci-dessous (analyse du 2026-09-03, cette session).

## Global Constraints

- Aucun prix ne change sans distance : un client qui n'envoie pas `distance_km` garde le comportement historique (frais client conservés), exactement comme `Tarification::prixRetenu()` en tranche 1.
- Aucune route `v1.0` renommée/supprimée ; `validerPanier`, `createOrder`, `getAppVersion`, `getParameters` restent intacts et rétrocompatibles.
- Les deux chemins de création d'une commande (`creerDepuisPanier` et `CreateOrder`) doivent facturer pareil (CLAUDE.md règle 6, même esprit).
- Tests serveur sans dépendre des 51 migrations en attente : ni `RefreshDatabase`, ni lecture des tables `clando`, `order_details`, `tarifs`, `locations`, `users` ; doublures liées au conteneur (`$this->app->instance(...)`), `Http::fake()`, `config()->set()`.
- Préfixe `v2` : chaque route documente son authentification (CLAUDE.md règle 23) ; `config` est publique (aucune identité, données déjà publiques).
- Pas de secret dans le code, les docs, les commits ; nouvelles variables `.env` documentées **par leur nom** dans `.env.example`.
- Pas de commit sans demande explicite de l'utilisateur (`git status --short --branch` avant chaque étape).
- Règle 7 : `coursier_request_screen.dart` et `delivery_request_screen.dart` bougent ensemble — cette tranche ne les touche pas.
- Français partout (code, commentaires, messages).

---

## §0 Spécification

### §0.1 Frais de livraison du panier (`validerPanier` → `OrderController::creerDepuisPanier`)

**Aujourd'hui.** L'app envoie `delivery_fees` (calculé en tranche 1 par `/v2/devis` quand connectée, estimation locale hors connexion), `reception_mode` (`AFC` = retrait au comptoir, `LIVRAISON`, ou `delivery` par défaut), `delivery_address` (texte), `articles`, `cle_unique`. Aucune coordonnée, aucune distance. Le serveur enregistre `delivery_fees` tel quel et calcule `price = articles + frais`. Le point de livraison est résolu par `PointDeLivraison::resoudre()` (coordonnées transmises → lieu par id → lieu par nom d'adresse → position du compte → point de retrait), mais la distance du client vient de la position du téléphone vers un `pouletAfcLocation` **codé en dur** dans `cart_screen.dart` (9.298160757436905, 13.399066915343388).

**Cible.** Nouvelle classe `App\Support\FraisDeLivraison` :

```
calculer(mixed $fraisClient, mixed $distanceClientKm, ?string $receptionMode, ?array $pointDeRetrait, ?array $pointDeLivraison): array{frais:int, distance_km:?float, source:string}
```

1. `reception_mode` ∈ {`afc`, `retrait`, `pickup`, `sur_place`} (insensible à la casse) → `frais=0`, `source='retrait'`.
2. Sinon, si `distanceClientKm` numérique > 0 :
   - si les deux points sont connus et `distanceClient < volDOiseau × 0.95` → distance retenue = `volDOiseau × 1.25` (`FACTEUR_ROUTE`), `source='estimation_serveur'`, `Log::warning('FraisDeLivraison: distance client incohérente', [...])` ;
   - sinon distance retenue = distance client, `source='client'`.
   - `frais = Tarification::prixRetenu(Tarif::LIVRAISON, $fraisClient, $distanceRetenue)`.
3. Sinon (ancien build, pas de distance) : `frais = prixRetenu(LIVRAISON, $fraisClient, null) ?? 0`, `distance_km=null`, `source='legacy'`. On n'estime **pas** à partir des points : le client afficherait un total différent de celui enregistré.

Le point de retrait vient de `PointDeLivraison::pointDeRetrait(): ?array{id:int, nom:string, lat:float, lon:float}` (nouvelle méthode publique, à partir de l'actuelle `lieuParDefaut()` privée + `nomDuLieuParDefaut()`). Le point de livraison est le `[lat, lon]` déjà résolu par `resoudre()` dans `creerDepuisPanier`.

`creerDepuisPanier` et `CreateOrder` utilisent `$frais['frais']` partout où ils lisaient `$request->delivery_fees` (commission comprise). `CreateOrder` n'est plus appelé par aucune des 4 apps (grep `CreateOrder` dans `plouletafcapp`, `pouletafc_agent`, `empolyeeafc` : rien) mais reste pour les anciens builds — il doit facturer pareil.

### §0.2 Détours (`App\Support\RecalculDistanceDetours`)

**Aujourd'hui.** Lit `parameters` directement, `base_price = max(km × clando_kilometer, min)` : ni arrondi à 50, ni majoration VIP, ni grille `tarifs`. Un client VIP qui ajoute un détour perd sa majoration ; une grille horaire n'est pas appliquée. OSRM appelé inline, non testable.

**Cible.** Trois méthodes statiques publiques :

- `pointsDePassage(Clando $clando, iterable $detours): array` — liste de `"lon,lat"` (départ, détours dans l'ordre, destination).
- `distanceParOsrm(array $points, string $ref = ''): ?float` — km, `null` + `Log::warning` si réponse inattendue ou injoignable (`Http::timeout(10)`).
- `appliquer(Clando $clando, float $distanceKm): Clando` — `distance` et `base_price = app(Tarification::class)->devis(Tarif::CLANDO, $distanceKm, $clando->type === 'vip')->prix`, **sans** `save()`.
- `recalculer(Clando $clando): void` — orchestre les trois, `save()` à la fin. Sans détour ou sans distance : sans effet (inchangé).

### §0.3 `GET|POST /api/v2/config?app=client|agent`

Publique, `throttle:60,1`, même contrôleur pour GET et POST (règle 2). Réponse :

```json
{"response":200,"data":{
  "app":"client",
  "genere_a":"2026-09-03T10:00:00+01:00",
  "version":{"code":40,"min_code":38,"nom":"1.0.5","download_url":"https://…"},
  "point_de_retrait":{"id":12,"nom":"Marché central — Centre","lat":9.2981,"lon":13.3990},
  "contact":{"telephone":"697526980","whatsapp":null},
  "fonctionnalites":{"coursier":true,"vip":true,"promotions":true,"paiement_om":true}
}}
```

Sources : `version` = mêmes clés que `AppVersionController` (`config('mobile_app.android|agent')`, `MobileAppService::playStoreUrl()`, routes `app.agent.apk` / `shop.app.android.apk`) ; `point_de_retrait` = `PointDeLivraison::pointDeRetrait()` (`null` si non configuré) ; `contact` et `fonctionnalites` = nouvelles clés de `config/mobile_app.php` lues depuis `.env` (`MOBILE_APP_CONTACT_TELEPHONE`, `MOBILE_APP_CONTACT_WHATSAPP`, `MOBILE_APP_FONCTION_COURSIER`, `MOBILE_APP_FONCTION_VIP`, `MOBILE_APP_FONCTION_PROMOTIONS`, `MOBILE_APP_FONCTION_PAIEMENT_OM`, toutes `true` par défaut). Les drapeaux en base (éditables depuis le tableau de bord) viendront en tranche 3 avec leur migration — ce contrat JSON ne changera pas. `tarifs` n'est **pas** exposé : les apps ne calculent plus rien, elles appellent `/v2/devis`.

### §0.4 Apps

**`plouletafcapp`** — `lib/services/remote_config_service.dart` : `RemoteConfig` (modèle, `fromJson`, `fonction(nom, {defaut})`), `RemoteConfigService.instance.charger()` (GET `/api/v2/config?app=client`, cache `SharedPreferences['remote_config_json']`, repli sur le cache en cas d'échec), `config` (dernière valeur, cache compris). Chargée au démarrage (`tab_screen.dart`). Consommation : `cart_screen.dart` lit `pointDeRetrait` (repli sur les coordonnées codées en dur, renommées `_pointDeRetraitParDefaut`) ; envoie `distance_km`, `delivery_lat`, `delivery_lon` à `validerPanier` (3 appels : 2 dans `payment_options_screen.dart`, 1 dans `order_queue_service.dart` + `QueuedOrder` sérialisé avec valeurs par défaut pour la file déjà persistée) ; l'estimation locale hors connexion disparaît (`fees=0`, mention « frais calculés à l'envoi », le serveur fixe les frais grâce à `distance_km`) ; l'entrée « Coursier » (`home_screen.dart:1141`, `destinationResearch.dart:269`) est masquée si `fonction('coursier')` est faux. `checkForAppUpdate(context, {RemoteConfig? config})` lit `version` depuis la config quand elle est là, sinon `getAppVersion` (inchangé).

**`pouletafc_agent`** — même `remote_config_service.dart` (`app=agent`, package `afc_chicken_delivery`), chargée dans `home_screen.dart`, et `checkForAppUpdate(context, {RemoteConfig? config})` identique. Aucun drapeau consommé pour l'instant (rien à masquer côté agent) : fondation documentée.

### §0.5 Hors périmètre

`RecalculDistanceDetours` continue d'appeler OSRM public (pas de clé, pas de cache) ; drapeaux en base + écran d'administration (tranche 3) ; `SurchargeArrets` (déjà serveur) ; envoi de `id_location` par l'app (les coordonnées transmises suffisent, elles passent en tête de `PointDeLivraison::candidats()`).

---

## Carte des fichiers

**pouletafc**
- Create `app/Support/FraisDeLivraison.php` — décision des frais (§0.1).
- Modify `app/Support/PointDeLivraison.php` — `pointDeRetrait()` public.
- Modify `app/Http/Controllers/API/OrderController.php:30-104` (`creerDepuisPanier`) et `:106-330` (`CreateOrder`).
- Modify `app/Support/RecalculDistanceDetours.php` — découpage §0.2.
- Create `app/Http/Controllers/API/ConfigController.php`, `app/Support/ConfigurationMobile.php`.
- Modify `config/mobile_app.php`, `.env.example`, `routes/api.php` (groupe `v2`).
- Tests : `tests/Unit/FraisDeLivraisonTest.php`, `tests/Unit/RecalculDistanceDetoursTest.php`, `tests/Feature/ConfigApiTest.php`.

**plouletafcapp**
- Create `lib/services/remote_config_service.dart`, `test/remote_config_service_test.dart`.
- Modify `lib/screens/tab_screen/tab_screen.dart`, `lib/screens/cart_screen/cart_screen.dart`, `lib/screens/payment_options_screen/payment_options_screen.dart`, `lib/services/order_queue_service.dart`, `lib/services/app_update_service.dart`, `lib/screens/home_screen/home_screen.dart`, `lib/screens/clando/destinationResearch.dart`.

**pouletafc_agent**
- Create `lib/services/remote_config_service.dart`, `test/remote_config_service_test.dart`.
- Modify `lib/services/app_update_service.dart`, `lib/screens/home_screen/home_screen.dart`.

---

### Task 1 : `FraisDeLivraison` + `PointDeLivraison::pointDeRetrait()` + branchement dans les deux chemins de commande

**Files:**
- Create: `app/Support/FraisDeLivraison.php`
- Modify: `app/Support/PointDeLivraison.php` (ajouter `pointDeRetrait()` à côté de `nomDuLieuParDefaut()`)
- Modify: `app/Http/Controllers/API/OrderController.php` (`creerDepuisPanier`, `CreateOrder`)
- Test: `tests/Unit/FraisDeLivraisonTest.php`

**Interfaces:**
- Consumes: `App\Support\Tarification::prixRetenu(string $service, mixed $prixClient, mixed $distanceKm, bool $vip=false): ?int`, `Tarification::arrondi50(float): int`, `App\Support\Distance::metres(float,float,float,float): float`, `App\Models\Tarif::LIVRAISON`.
- Produces: `FraisDeLivraison::calculer(...)` (§0.1), constantes `FraisDeLivraison::FACTEUR_ROUTE = 1.25`, `TOLERANCE_VOL_D_OISEAU = 0.95`, `MODES_RETRAIT`; `PointDeLivraison::pointDeRetrait(): ?array{id:int,nom:string,lat:float,lon:float}` (réutilisée en Task 3).

- [ ] **Step 1 : test rouge**

```php
<?php
// tests/Unit/FraisDeLivraisonTest.php
namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\Distance;
use App\Support\FraisDeLivraison;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Frais de livraison d'une commande : décidés par le serveur à partir de la
 * distance, jamais recopiés du téléphone. Fonction pure (doublure de la
 * grille) : la base locale n'a pas les tables nécessaires (51 migrations en
 * attente).
 */
class FraisDeLivraisonTest extends TestCase
{
    /** Point de retrait (Garoua) et point de livraison à ~3,7 km à vol d'oiseau. */
    private const RETRAIT = ['id' => 1, 'nom' => 'Comptoir', 'lat' => 9.2982, 'lon' => 13.3991];
    private const LIVRAISON = ['lat' => 9.3300, 'lon' => 13.3900];

    private function frais(): FraisDeLivraison
    {
        // 200 F/km, minimum 400 : assez cher pour que le plancher ne masque rien.
        $parametres = new Parameter(['command_kilometer' => 200, 'min_price_command' => 400]);

        return new FraisDeLivraison(new Tarification(new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        }));
    }

    public function test_un_retrait_au_comptoir_ne_coute_rien_quel_que_soit_le_reste(): void
    {
        foreach (['AFC', 'afc', 'retrait', 'Pickup', 'sur_place'] as $mode) {
            $r = $this->frais()->calculer('650', '10', $mode, self::RETRAIT, self::LIVRAISON);
            $this->assertSame(['frais' => 0, 'distance_km' => null, 'source' => 'retrait'], $r, $mode);
        }
    }

    public function test_avec_une_distance_le_serveur_calcule_et_ignore_les_frais_client(): void
    {
        Log::shouldReceive('warning')->once(); // prix client ≠ prix serveur (Tarification)

        $r = $this->frais()->calculer('100', '10', 'LIVRAISON', null, null);

        $this->assertSame(2000, $r['frais']);
        $this->assertSame(10.0, $r['distance_km']);
        $this->assertSame('client', $r['source']);
    }

    public function test_une_distance_coherente_avec_les_points_est_retenue_telle_quelle(): void
    {
        Log::shouldReceive('warning')->never();

        $r = $this->frais()->calculer(2000, 10, 'delivery', self::RETRAIT, self::LIVRAISON);

        $this->assertSame(2000, $r['frais']);
        $this->assertSame('client', $r['source']);
    }

    public function test_une_distance_plus_courte_que_le_vol_d_oiseau_est_remplacee_par_une_estimation(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();

        $volDOiseauKm = Distance::metres(self::RETRAIT['lat'], self::RETRAIT['lon'], self::LIVRAISON['lat'], self::LIVRAISON['lon']) / 1000;
        $attendu = Tarification::arrondi50($volDOiseauKm * FraisDeLivraison::FACTEUR_ROUTE * 200);

        $r = $this->frais()->calculer('200', '1', 'LIVRAISON', self::RETRAIT, self::LIVRAISON);

        $this->assertSame($attendu, $r['frais']);
        $this->assertEqualsWithDelta($volDOiseauKm * FraisDeLivraison::FACTEUR_ROUTE, $r['distance_km'], 0.001);
        $this->assertSame('estimation_serveur', $r['source']);
    }

    public function test_sans_distance_les_frais_client_sont_conserves_comme_avant(): void
    {
        Log::shouldReceive('warning')->never();

        $r = $this->frais()->calculer('800', null, 'LIVRAISON', self::RETRAIT, self::LIVRAISON);

        $this->assertSame(['frais' => 800, 'distance_km' => null, 'source' => 'legacy'], $r);
        $this->assertSame(0, $this->frais()->calculer('abc', '', 'LIVRAISON', null, null)['frais']);
        $this->assertSame(0, $this->frais()->calculer(null, 0, null, null, null)['frais']);
    }
}
```

- [ ] **Step 2 : `php artisan test --filter FraisDeLivraisonTest`** → échec « Class App\Support\FraisDeLivraison not found ».

- [ ] **Step 3 : implémentation**

```php
<?php
// app/Support/FraisDeLivraison.php
namespace App\Support;

use App\Models\Tarif;
use Illuminate\Support\Facades\Log;

/**
 * Frais de livraison d'une commande de panier, décidés par le serveur.
 *
 * Jusqu'ici `delivery_fees` était recopié tel quel depuis le téléphone —
 * calculé par l'application à partir d'une position et d'un point de
 * retrait codé en dur. Ici : la distance envoyée par l'application est
 * acceptée si elle n'est pas plus courte que le vol d'oiseau entre le point
 * de retrait et le point de livraison résolu (PointDeLivraison) ; sinon une
 * estimation routière la remplace. Le montant vient toujours de Tarification.
 *
 * Sans distance (ancien build), les frais client sont conservés : le total
 * enregistré doit rester celui que le client a vu à l'écran.
 */
class FraisDeLivraison
{
    /** Une route dépasse rarement le vol d'oiseau de plus d'un quart, en ville. */
    public const FACTEUR_ROUTE = 1.25;

    /** Même marge que DevisController pour les arrondis GPS. */
    public const TOLERANCE_VOL_D_OISEAU = 0.95;

    /** Valeurs de `reception_mode` qui signifient « le client vient chercher ». */
    public const MODES_RETRAIT = ['afc', 'retrait', 'pickup', 'sur_place'];

    public function __construct(private Tarification $tarification)
    {
    }

    /**
     * @param  array{lat: float, lon: float}|null  $pointDeRetrait
     * @param  array{lat: float, lon: float}|null  $pointDeLivraison
     * @return array{frais: int, distance_km: float|null, source: string}
     */
    public function calculer(
        mixed $fraisClient,
        mixed $distanceClientKm,
        ?string $receptionMode,
        ?array $pointDeRetrait,
        ?array $pointDeLivraison,
    ): array {
        if (in_array(mb_strtolower(trim((string) $receptionMode)), self::MODES_RETRAIT, true)) {
            return ['frais' => 0, 'distance_km' => null, 'source' => 'retrait'];
        }

        if (! is_numeric($distanceClientKm) || (float) $distanceClientKm <= 0) {
            return [
                'frais' => $this->tarification->prixRetenu(Tarif::LIVRAISON, $fraisClient, null) ?? 0,
                'distance_km' => null,
                'source' => 'legacy',
            ];
        }

        $distance = (float) $distanceClientKm;
        $source = 'client';

        $volDOiseau = $this->volDOiseauKm($pointDeRetrait, $pointDeLivraison);

        if ($volDOiseau !== null && $distance < $volDOiseau * self::TOLERANCE_VOL_D_OISEAU) {
            $estimation = $volDOiseau * self::FACTEUR_ROUTE;
            Log::warning('FraisDeLivraison: distance client incohérente, estimation serveur retenue', [
                'distance_client_km' => $distance,
                'vol_d_oiseau_km' => $volDOiseau,
                'estimation_km' => $estimation,
            ]);
            $distance = $estimation;
            $source = 'estimation_serveur';
        }

        return [
            'frais' => $this->tarification->prixRetenu(Tarif::LIVRAISON, $fraisClient, $distance) ?? 0,
            'distance_km' => $distance,
            'source' => $source,
        ];
    }

    private function volDOiseauKm(?array $a, ?array $b): ?float
    {
        if (! isset($a['lat'], $a['lon'], $b['lat'], $b['lon'])) {
            return null;
        }

        return Distance::metres((float) $a['lat'], (float) $a['lon'], (float) $b['lat'], (float) $b['lon']) / 1000;
    }
}
```

Dans `app/Support/PointDeLivraison.php`, juste après `nomDuLieuParDefaut()` :

```php
    /**
     * Le point de retrait désigné, avec ses coordonnées — pour calculer une
     * distance de livraison (FraisDeLivraison) et pour l'exposer aux
     * applications (/api/v2/config) à la place d'un point codé en dur.
     *
     * @return array{id: int, nom: string, lat: float, lon: float}|null
     */
    public function pointDeRetrait(): ?array
    {
        $idLieu = \App\Models\Parameter::active()?->default_pickup_location_id;
        $lieu = $idLieu ? \App\Models\Location::find($idLieu) : null;
        $lat = $this->nombre($lieu?->latitude);
        $lon = $this->nombre($lieu?->longitude);

        if (! $lieu || $lat === null || $lon === null) {
            return null;
        }

        $quartier = $lieu->quarter?->name;

        return [
            'id' => (int) $lieu->id,
            'nom' => $quartier ? $lieu->name . ' — ' . $quartier : $lieu->name,
            'lat' => $lat,
            'lon' => $lon,
        ];
    }
```

- [ ] **Step 4 : `php artisan test --filter FraisDeLivraisonTest`** → 5 passed.

- [ ] **Step 5 : brancher `creerDepuisPanier`** (`OrderController.php`, remplacer la ligne `$fraisDeLivraison = (float) $request->input('delivery_fees', 0);`) :

```php
        /*
         | Frais décidés par le serveur (App\Support\FraisDeLivraison) : la
         | distance envoyée par l'application est bornée par le vol d'oiseau
         | entre le point de retrait et le point de livraison résolu ci-dessus,
         | un retrait au comptoir ne coûte rien, et un ancien build sans
         | distance garde ses frais tels quels.
         */
        $frais = app(\App\Support\FraisDeLivraison::class)->calculer(
            $request->input('delivery_fees'),
            $request->input('distance_km'),
            $request->input('reception_mode'),
            app(\App\Support\PointDeLivraison::class)->pointDeRetrait(),
            ($lat !== null && $lon !== null) ? ['lat' => $lat, 'lon' => $lon] : null,
        );
        $fraisDeLivraison = (float) $frais['frais'];
```

- [ ] **Step 6 : brancher `CreateOrder`** — après le bloc `[$lat, $lon, $origineDuPoint] = ...->resoudre($request, $user);`, ajouter le même bloc (avec `$request->input('delivery_fees')` etc.) puis remplacer : dans `commissionLivraison((float) $request->input('delivery_fees', 0), (float) $request->price)` → `commissionLivraison($fraisDeLivraison, (float) $request->price)` ; `'price' =>$totalamount + $request->delivery_fees` → `'price' => $totalamount + $fraisDeLivraison` ; `'delivery_fees'=>$request->delivery_fees` → `'delivery_fees' => $fraisDeLivraison` ; `'price' => $panierReel + (float) $request->delivery_fees` → `'price' => $panierReel + $fraisDeLivraison`. Le `dejaPassee(... (float) ($totalamount + $request->delivery_fees) ...)` du contrôle de doublon reste sur la valeur client (il compare avec ce que le client renvoie à l'identique) — laisser tel quel et le commenter : `// Empreinte de doublon : comparée à ce que le client renvoie, donc sur ses propres valeurs.`

- [ ] **Step 7 : `php -l` sur les trois fichiers ; `php artisan test --filter "FraisDeLivraisonTest|PrixRetenuTest"`** → tout vert ; `grep -n 'request->delivery_fees\|delivery_fees' app/Http/Controllers/API/OrderController.php` ne doit plus montrer que la ligne du contrôle de doublon et les lectures `$frais`/`$fraisDeLivraison`.

- [ ] **Step 8 : pas de commit** (l'utilisateur commite) ; `git status --short --branch`.

---

### Task 2 : `RecalculDistanceDetours` sur `Tarification`

**Files:**
- Modify: `app/Support/RecalculDistanceDetours.php`
- Test: `tests/Unit/RecalculDistanceDetoursTest.php`

**Interfaces:**
- Consumes: `Tarification::devis(string $service, float $km, bool $vip): Devis` (`->prix`), `Clando` (`latMyPosition`, `lonMyPosition`, `latDestination`, `lonDestination`, `type`, `distance`, `base_price`, `ref`).
- Produces: `RecalculDistanceDetours::pointsDePassage(Clando, iterable): array`, `distanceParOsrm(array, string=''): ?float`, `appliquer(Clando, float): Clando`, `recalculer(Clando): void` (signature inchangée pour `ClandoController::addClandoStop`).

- [ ] **Step 1 : test rouge**

```php
<?php
// tests/Unit/RecalculDistanceDetoursTest.php
namespace Tests\Unit;

use App\Models\Clando;
use App\Models\ClandoStop;
use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use App\Support\RecalculDistanceDetours;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Détours d'une course : la distance vient d'OSRM (simulé ici), le prix de
 * base de Tarification — arrondi et majoration VIP compris, ce que l'ancien
 * calcul direct sur `parameters` oubliait. Aucun accès base : modèles non
 * persistés, grille doublée.
 */
class RecalculDistanceDetoursTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $parametres = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50]);
        $this->app->instance(GrilleTarifaire::class, new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    private function course(string $type = 'classic'): Clando
    {
        return new Clando([
            'ref' => 'TEST', 'type' => $type,
            'latMyPosition' => 9.30, 'lonMyPosition' => 13.39,
            'latDestination' => 9.32, 'lonDestination' => 13.40,
            'price' => 1050, 'base_price' => 1050, 'distance' => 4.2,
        ]);
    }

    public function test_les_points_de_passage_vont_du_depart_aux_detours_puis_a_la_destination(): void
    {
        $detours = [new ClandoStop(['lat' => 9.31, 'lon' => 13.395]), new ClandoStop(['lat' => 9.315, 'lon' => 13.398])];

        $this->assertSame(
            ['13.39,9.3', '13.395,9.31', '13.398,9.315', '13.4,9.32'],
            RecalculDistanceDetours::pointsDePassage($this->course(), $detours),
        );
    }

    public function test_osrm_donne_la_distance_en_kilometres(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 4200]]])]);

        $this->assertSame(4.2, RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32']));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/route/v1/driving/13.39,9.3;13.4,9.32'));
    }

    public function test_une_reponse_osrm_inattendue_ou_une_panne_donne_null(): void
    {
        Log::shouldReceive('warning')->twice();

        Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'NoRoute'])]);
        $this->assertNull(RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32'], 'REF'));

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertNull(RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32'], 'REF'));
    }

    public function test_appliquer_recalcule_la_base_avec_arrondi_et_vip(): void
    {
        $classique = RecalculDistanceDetours::appliquer($this->course('classic'), 6.1);
        $this->assertSame(6.1, (float) $classique->distance);
        $this->assertSame(1550, (int) $classique->base_price); // 6.1×250=1525 → 1550

        $vip = RecalculDistanceDetours::appliquer($this->course('vip'), 6.1);
        $this->assertSame(2350, (int) $vip->base_price); // 1550 + 50 % = 2325 → 2350
    }
}
```

- [ ] **Step 2 : `php artisan test --filter RecalculDistanceDetoursTest`** → échec (méthodes absentes).

- [ ] **Step 3 : implémentation** (remplacer tout le corps de la classe, conserver le doc-bloc de classe existant) :

```php
class RecalculDistanceDetours
{
    /**
     * Recalcule `distance` et `base_price` à partir de tous les détours
     * actuels de la course. Sans effet si la course n'a aucun détour ou si
     * OSRM est injoignable — la distance/le prix précédents restent en place
     * plutôt que d'écraser une valeur correcte par une absente.
     */
    public static function recalculer(Clando $clando): void
    {
        $detours = $clando->stops()->where('type', 'detour')->orderBy('id')->get();

        if ($detours->isEmpty()) {
            return;
        }

        $distanceKm = self::distanceParOsrm(self::pointsDePassage($clando, $detours), (string) $clando->ref);

        if ($distanceKm === null) {
            return;
        }

        self::appliquer($clando, $distanceKm)->save();
    }

    /**
     * Départ, détours dans l'ordre, destination — au format « lon,lat » d'OSRM.
     *
     * @param  iterable<\App\Models\ClandoStop>  $detours
     * @return list<string>
     */
    public static function pointsDePassage(Clando $clando, iterable $detours): array
    {
        $points = ["{$clando->lonMyPosition},{$clando->latMyPosition}"];
        foreach ($detours as $detour) {
            $points[] = "{$detour->lon},{$detour->lat}";
        }
        $points[] = "{$clando->lonDestination},{$clando->latDestination}";

        return $points;
    }

    /** Distance routière en km, ou null si OSRM ne répond pas correctement. */
    public static function distanceParOsrm(array $points, string $ref = ''): ?float
    {
        $url = 'http://router.project-osrm.org/route/v1/driving/' . implode(';', $points) . '?overview=false';

        try {
            $data = Http::timeout(10)->get($url)->json();

            if (($data['code'] ?? null) !== 'Ok' || ! isset($data['routes'][0]['distance'])) {
                Log::warning('RecalculDistanceDetours: réponse OSRM inattendue', ['ref' => $ref, 'data' => $data]);

                return null;
            }

            return $data['routes'][0]['distance'] / 1000;
        } catch (\Throwable $e) {
            Log::warning('RecalculDistanceDetours: OSRM injoignable - ' . $e->getMessage(), ['ref' => $ref]);

            return null;
        }
    }

    /**
     * Nouvelle distance et nouveau prix de base — par le même moteur que le
     * prix initial (App\Support\Tarification) : arrondi à 50, majoration VIP
     * et grille horaire compris. Ne sauvegarde pas.
     */
    public static function appliquer(Clando $clando, float $distanceKm): Clando
    {
        $clando->distance = $distanceKm;
        $clando->base_price = app(Tarification::class)
            ->devis(Tarif::CLANDO, $distanceKm, $clando->type === 'vip')
            ->prix;

        return $clando;
    }
}
```

Imports à ajouter : `use App\Models\Tarif;` ; retirer `use App\Models\Parameter;`.

- [ ] **Step 4 : `php artisan test --filter RecalculDistanceDetoursTest`** → 4 passed ; `php -l`.

---

### Task 3 : `GET|POST /api/v2/config`

**Files:**
- Create: `app/Support/ConfigurationMobile.php`, `app/Http/Controllers/API/ConfigController.php`
- Modify: `config/mobile_app.php` (clés `contact`, `fonctionnalites`), `.env.example`, `routes/api.php` (groupe `v2`)
- Test: `tests/Feature/ConfigApiTest.php`

**Interfaces:**
- Consumes: `PointDeLivraison::pointDeRetrait()` (Task 1), `App\Services\MobileAppService::playStoreUrl(): ?string` (classe sans constructeur, injectable), routes nommées `app.agent.apk`, `shop.app.android.apk`.
- Produces: `ConfigurationMobile::pour(string $app): array` (forme §0.3), route `v2.config.get` / `v2.config.post`.

- [ ] **Step 1 : test rouge**

```php
<?php
// tests/Feature/ConfigApiTest.php
namespace Tests\Feature;

use App\Support\PointDeLivraison;
use Tests\TestCase;

/** /api/v2/config : tout ce qu'une application lit au démarrage. Sans base (config() + doublure). */
class ConfigApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mobile_app.android.version_code', 40);
        config()->set('mobile_app.android.min_version_code', 38);
        config()->set('mobile_app.android.version', '1.0.5');
        config()->set('mobile_app.agent.version_code', 7);
        config()->set('mobile_app.agent.min_version_code', 3);
        config()->set('mobile_app.contact.telephone', '697000000');
        config()->set('mobile_app.contact.whatsapp', null);
        config()->set('mobile_app.fonctionnalites.coursier', false);

        $this->app->instance(PointDeLivraison::class, new class extends PointDeLivraison {
            public function pointDeRetrait(): ?array
            {
                return ['id' => 12, 'nom' => 'Marché — Centre', 'lat' => 9.2981, 'lon' => 13.399];
            }
        });
    }

    public function test_la_configuration_cliente_est_complete(): void
    {
        $this->getJson('/api/v2/config')
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.app', 'client')
            ->assertJsonPath('data.version.code', 40)
            ->assertJsonPath('data.version.min_code', 38)
            ->assertJsonPath('data.version.nom', '1.0.5')
            ->assertJsonPath('data.point_de_retrait.id', 12)
            ->assertJsonPath('data.point_de_retrait.lat', 9.2981)
            ->assertJsonPath('data.contact.telephone', '697000000')
            ->assertJsonPath('data.contact.whatsapp', null)
            ->assertJsonPath('data.fonctionnalites.coursier', false)
            ->assertJsonPath('data.fonctionnalites.vip', true)
            ->assertJsonStructure(['data' => ['genere_a', 'version' => ['download_url']]]);
    }

    public function test_la_configuration_agent_porte_sa_propre_version(): void
    {
        $this->postJson('/api/v2/config', ['app' => 'agent'])
            ->assertOk()
            ->assertJsonPath('data.app', 'agent')
            ->assertJsonPath('data.version.code', 7)
            ->assertJsonPath('data.version.min_code', 3);
    }

    public function test_une_app_inconnue_est_refusee(): void
    {
        $this->getJson('/api/v2/config?app=web')->assertStatus(422);
    }

    public function test_sans_point_de_retrait_configure_le_champ_vaut_null(): void
    {
        $this->app->instance(PointDeLivraison::class, new class extends PointDeLivraison {
            public function pointDeRetrait(): ?array { return null; }
        });

        $this->getJson('/api/v2/config')->assertJsonPath('data.point_de_retrait', null);
    }
}
```

- [ ] **Step 2 : `php artisan test --filter ConfigApiTest`** → 404/échec.

- [ ] **Step 3 : configuration** — dans `config/mobile_app.php`, avant le `];` final :

```php
    /*
    |--------------------------------------------------------------------------
    | Configuration distante (/api/v2/config)
    |--------------------------------------------------------------------------
    | Lue par les deux applications au démarrage. Les drapeaux permettent de
    | masquer une fonctionnalité sans republier un build. Éditables depuis
    | .env pour l'instant ; passeront en base (tableau de bord) plus tard sans
    | changer le contrat JSON.
    */
    'contact' => [
        'telephone' => env('MOBILE_APP_CONTACT_TELEPHONE', '697526980'),
        'whatsapp' => env('MOBILE_APP_CONTACT_WHATSAPP'),
    ],

    'fonctionnalites' => [
        'coursier' => (bool) env('MOBILE_APP_FONCTION_COURSIER', true),
        'vip' => (bool) env('MOBILE_APP_FONCTION_VIP', true),
        'promotions' => (bool) env('MOBILE_APP_FONCTION_PROMOTIONS', true),
        'paiement_om' => (bool) env('MOBILE_APP_FONCTION_PAIEMENT_OM', true),
    ],
```

`.env.example`, après `MOBILE_APP_AGENT_MIN_VERSION_CODE=0` :

```
# Configuration distante lue par les apps (/api/v2/config). Contact affiché
# au client ; drapeaux à false pour masquer une fonctionnalité sans republier.
MOBILE_APP_CONTACT_TELEPHONE=697526980
MOBILE_APP_CONTACT_WHATSAPP=
MOBILE_APP_FONCTION_COURSIER=true
MOBILE_APP_FONCTION_VIP=true
MOBILE_APP_FONCTION_PROMOTIONS=true
MOBILE_APP_FONCTION_PAIEMENT_OM=true
```

- [ ] **Step 4 : support + contrôleur + route**

```php
<?php
// app/Support/ConfigurationMobile.php
namespace App\Support;

use App\Services\MobileAppService;
use Carbon\CarbonImmutable;

/**
 * Ce qu'une application mobile lit au démarrage (/api/v2/config) : version
 * disponible et minimale, point de retrait, contact, fonctionnalités
 * activables. Agrège des sources déjà existantes — ne crée aucune donnée.
 */
class ConfigurationMobile
{
    public const APPS = ['client', 'agent'];

    public function __construct(private MobileAppService $app, private PointDeLivraison $pointDeLivraison)
    {
    }

    public function pour(string $app): array
    {
        return [
            'app' => $app,
            'genere_a' => CarbonImmutable::now()->toIso8601String(),
            'version' => $this->version($app),
            'point_de_retrait' => $this->pointDeLivraison->pointDeRetrait(),
            'contact' => [
                'telephone' => config('mobile_app.contact.telephone') ?: null,
                'whatsapp' => config('mobile_app.contact.whatsapp') ?: null,
            ],
            'fonctionnalites' => array_map(
                fn ($actif) => (bool) $actif,
                (array) config('mobile_app.fonctionnalites', []),
            ),
        ];
    }

    /** Mêmes valeurs que AppVersionController, qui reste servi aux anciens builds. */
    private function version(string $app): array
    {
        if ($app === 'agent') {
            return [
                'code' => (int) config('mobile_app.agent.version_code', 0),
                'min_code' => (int) config('mobile_app.agent.min_version_code', 0),
                'nom' => config('mobile_app.agent.version'),
                'download_url' => route('app.agent.apk'),
            ];
        }

        return [
            'code' => (int) config('mobile_app.android.version_code', 0),
            'min_code' => (int) config('mobile_app.android.min_version_code', 0),
            'nom' => config('mobile_app.android.version'),
            'download_url' => $this->app->playStoreUrl() ?: route('shop.app.android.apk'),
        ];
    }
}
```

```php
<?php
// app/Http/Controllers/API/ConfigController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ConfigurationMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuration distante d'une application mobile (voir ConfigurationMobile).
 *
 * Publique, **sans jeton** : rien ici n'est propre à un utilisateur, et les
 * mêmes valeurs sont déjà lisibles par getAppVersion/getParameters. Limitée
 * par throttle dans routes/api.php.
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request, ConfigurationMobile $configuration): JsonResponse
    {
        $valide = $request->validate([
            'app' => ['nullable', 'in:' . implode(',', ConfigurationMobile::APPS)],
        ]);

        return response()->json([
            'response' => 200,
            'data' => $configuration->pour($valide['app'] ?? 'client'),
        ]);
    }
}
```

`routes/api.php`, dans le groupe `prefix => 'v2'`, après les deux routes `devis` :

```php
    // Public : configuration de démarrage, rien de propre à un utilisateur (voir ConfigController).
    Route::get('config', 'ConfigController')->middleware('throttle:60,1')->name('v2.config.get');
    Route::post('config', 'ConfigController')->middleware('throttle:60,1')->name('v2.config.post');
```

- [ ] **Step 5 : `php artisan test --filter ConfigApiTest`** → 4 passed ; `php artisan route:list --path=v2` montre `config` GET+POST ; `php -l` sur les nouveaux fichiers.

---

### Task 4 : `plouletafcapp` — `RemoteConfigService` et consommation

**Files:**
- Create: `lib/services/remote_config_service.dart`, `test/remote_config_service_test.dart`
- Modify: `lib/screens/tab_screen/tab_screen.dart:315-326`, `lib/services/app_update_service.dart`, `lib/screens/cart_screen/cart_screen.dart` (`pouletAfcLocation`, `calculateDeliveryDistance`, `updateDeliveryPrice`, `details` du `PaymentOptionsScreen`, section facturation), `lib/screens/payment_options_screen/payment_options_screen.dart:247-256,300-302,332-340`, `lib/services/order_queue_service.dart` (`QueuedOrder` + `_processOrder`), `lib/screens/home_screen/home_screen.dart:1136`, `lib/screens/clando/destinationResearch.dart:266`.

**Interfaces:**
- Consumes: `/api/v2/config` (§0.3), `decisionMiseAJour(...)` existante.
- Produces: `RemoteConfig` (`version: RemoteVersion{code,minCode,nom,downloadUrl}`, `pointDeRetrait: PointDeRetrait?{id,nom,lat,lon}`, `contact: Contact{telephone,whatsapp}`, `fonctionnalites: Map<String,bool>`, `bool fonction(String nom, {bool defaut = true})`, `factory RemoteConfig.fromJson(Map<String,dynamic> data)`), `RemoteConfigService.instance` (`Future<RemoteConfig?> charger({http.Client? client})`, `RemoteConfig? get config`, `bool fonction(String nom, {bool defaut = true})`, `reinitialiserPourTests()`), constante `kApiBaseV2` (déjà dans `tarification_service.dart`, réutiliser).

- [ ] **Step 1 : test rouge** `test/remote_config_service_test.dart`

```dart
import 'dart:convert';

import 'package:clando/services/remote_config_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

Map<String, dynamic> _reponse({bool coursier = true}) => {
      'response': 200,
      'data': {
        'app': 'client',
        'genere_a': '2026-09-03T10:00:00+01:00',
        'version': {'code': 40, 'min_code': 38, 'nom': '1.0.5', 'download_url': 'https://pouletafc.com/apk'},
        'point_de_retrait': {'id': 12, 'nom': 'Marché — Centre', 'lat': 9.2981, 'lon': 13.399},
        'contact': {'telephone': '697000000', 'whatsapp': null},
        'fonctionnalites': {'coursier': coursier, 'vip': true},
      },
    };

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    RemoteConfigService.instance.reinitialiserPourTests();
  });

  group('RemoteConfig.fromJson', () {
    test('lit toutes les sections', () {
      final c = RemoteConfig.fromJson(_reponse()['data'] as Map<String, dynamic>);
      expect(c.version.code, 40);
      expect(c.version.minCode, 38);
      expect(c.version.downloadUrl, 'https://pouletafc.com/apk');
      expect(c.pointDeRetrait!.lat, 9.2981);
      expect(c.pointDeRetrait!.nom, 'Marché — Centre');
      expect(c.contact.telephone, '697000000');
      expect(c.contact.whatsapp, isNull);
      expect(c.fonction('coursier'), isTrue);
    });

    test('une fonctionnalité absente prend la valeur par défaut', () {
      final c = RemoteConfig.fromJson(_reponse()['data'] as Map<String, dynamic>);
      expect(c.fonction('inconnue'), isTrue);
      expect(c.fonction('inconnue', defaut: false), isFalse);
    });

    test('point de retrait null toléré', () {
      final data = _reponse()['data'] as Map<String, dynamic>;
      data['point_de_retrait'] = null;
      expect(RemoteConfig.fromJson(data).pointDeRetrait, isNull);
    });
  });

  group('RemoteConfigService.charger', () {
    test('appelle /v2/config?app=client et met en cache', () async {
      http.Request? recu;
      final client = MockClient((req) async {
        recu = req;
        return http.Response(jsonEncode(_reponse(coursier: false)), 200);
      });

      final c = await RemoteConfigService.instance.charger(client: client);

      expect(recu!.url.toString(), 'https://pouletafc.com/api/v2/config?app=client');
      expect(c!.fonction('coursier'), isFalse);
      expect(RemoteConfigService.instance.fonction('coursier'), isFalse);
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('remote_config_json'), isNotNull);
    });

    test('en cas de panne, retombe sur le cache', () async {
      SharedPreferences.setMockInitialValues({
        'remote_config_json': jsonEncode(_reponse(coursier: false)['data']),
      });
      final client = MockClient((_) async => throw http.ClientException('down'));

      final c = await RemoteConfigService.instance.charger(client: client);

      expect(c, isNotNull);
      expect(c!.fonction('coursier'), isFalse);
    });

    test('sans réseau ni cache, config null et fonctionnalités par défaut', () async {
      final client = MockClient((_) async => http.Response('erreur', 500));

      final c = await RemoteConfigService.instance.charger(client: client);

      expect(c, isNull);
      expect(RemoteConfigService.instance.fonction('coursier'), isTrue);
    });
  });
}
```

- [ ] **Step 2 : `flutter test test/remote_config_service_test.dart`** → échec de compilation (fichier absent).

- [ ] **Step 3 : implémentation** `lib/services/remote_config_service.dart`

```dart
import 'dart:convert';

import 'package:clando/services/tarification_service.dart' show kApiBaseV2;
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Configuration lue au démarrage depuis `GET /api/v2/config?app=client`
/// (pouletafc, `App\Support\ConfigurationMobile`). Ce que le serveur peut
/// changer sans republier l'application : version minimale, point de
/// retrait, contact, fonctionnalités activables.
class RemoteConfig {
  const RemoteConfig({
    required this.version,
    required this.pointDeRetrait,
    required this.contact,
    required this.fonctionnalites,
    this.genereA,
  });

  final RemoteVersion version;
  final PointDeRetrait? pointDeRetrait;
  final Contact contact;
  final Map<String, bool> fonctionnalites;
  final DateTime? genereA;

  /// Une fonctionnalité que le serveur ne connaît pas est considérée active
  /// par défaut : un serveur plus ancien ne doit rien masquer.
  bool fonction(String nom, {bool defaut = true}) => fonctionnalites[nom] ?? defaut;

  factory RemoteConfig.fromJson(Map<String, dynamic> data) {
    final flags = <String, bool>{};
    final brut = data['fonctionnalites'];
    if (brut is Map) {
      brut.forEach((k, v) => flags['$k'] = v == true || v == 1 || v == 'true');
    }
    final retrait = data['point_de_retrait'];
    final contact = data['contact'];
    return RemoteConfig(
      version: RemoteVersion.fromJson(data['version'] is Map ? Map<String, dynamic>.from(data['version']) : const {}),
      pointDeRetrait: retrait is Map ? PointDeRetrait.fromJson(Map<String, dynamic>.from(retrait)) : null,
      contact: Contact(
        telephone: contact is Map ? contact['telephone']?.toString() : null,
        whatsapp: contact is Map ? contact['whatsapp']?.toString() : null,
      ),
      fonctionnalites: flags,
      genereA: DateTime.tryParse(data['genere_a']?.toString() ?? ''),
    );
  }
}

class RemoteVersion {
  const RemoteVersion({required this.code, required this.minCode, this.nom, this.downloadUrl});
  final int code;
  final int minCode;
  final String? nom;
  final String? downloadUrl;

  factory RemoteVersion.fromJson(Map<String, dynamic> j) => RemoteVersion(
        code: (j['code'] as num?)?.toInt() ?? 0,
        minCode: (j['min_code'] as num?)?.toInt() ?? 0,
        nom: j['nom']?.toString(),
        downloadUrl: j['download_url']?.toString(),
      );
}

class PointDeRetrait {
  const PointDeRetrait({required this.id, required this.nom, required this.lat, required this.lon});
  final int id;
  final String nom;
  final double lat;
  final double lon;

  static PointDeRetrait? fromJson(Map<String, dynamic> j) {
    final lat = double.tryParse('${j['lat']}');
    final lon = double.tryParse('${j['lon']}');
    if (lat == null || lon == null) return null;
    return PointDeRetrait(id: (j['id'] as num?)?.toInt() ?? 0, nom: j['nom']?.toString() ?? '', lat: lat, lon: lon);
  }
}

class Contact {
  const Contact({this.telephone, this.whatsapp});
  final String? telephone;
  final String? whatsapp;
}

/// Singleton : chargé une fois au démarrage (tab_screen.dart), relu partout.
class RemoteConfigService {
  RemoteConfigService._();
  static final RemoteConfigService instance = RemoteConfigService._();

  static const String _cle = 'remote_config_json';
  static const String app = 'client';

  RemoteConfig? _config;
  RemoteConfig? get config => _config;

  bool fonction(String nom, {bool defaut = true}) => _config?.fonction(nom, defaut: defaut) ?? defaut;

  /// Réseau d'abord, cache ensuite ; `null` seulement sans les deux.
  Future<RemoteConfig?> charger({http.Client? client, Duration timeout = const Duration(seconds: 8)}) async {
    final c = client ?? http.Client();
    try {
      final reponse = await c
          .get(Uri.parse('$kApiBaseV2/config?app=$app'), headers: const {'Accept': 'application/json'})
          .timeout(timeout);
      if (reponse.statusCode == 200) {
        final json = jsonDecode(reponse.body);
        if (json is Map && json['response'] == 200 && json['data'] is Map) {
          final data = Map<String, dynamic>.from(json['data']);
          _config = RemoteConfig.fromJson(data);
          try {
            (await SharedPreferences.getInstance()).setString(_cle, jsonEncode(data));
          } catch (e) {
            debugPrint('RemoteConfig - cache non écrit : $e');
          }
          return _config;
        }
      }
      debugPrint('RemoteConfig - réponse inattendue : ${reponse.statusCode}');
    } catch (e) {
      debugPrint('RemoteConfig - indisponible : $e');
    }
    return _config = await _depuisLeCache();
  }

  Future<RemoteConfig?> _depuisLeCache() async {
    try {
      final brut = (await SharedPreferences.getInstance()).getString(_cle);
      if (brut == null) return null;
      return RemoteConfig.fromJson(Map<String, dynamic>.from(jsonDecode(brut)));
    } catch (e) {
      debugPrint('RemoteConfig - cache illisible : $e');
      return null;
    }
  }

  @visibleForTesting
  void reinitialiserPourTests() => _config = null;
}
```

- [ ] **Step 4 : `flutter test test/remote_config_service_test.dart`** → 6 passed.

- [ ] **Step 5 : démarrage** — `tab_screen.dart` `initState`, dans le `addPostFrameCallback` :

```dart
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final config = await RemoteConfigService.instance.charger();
      if (mounted) checkForAppUpdate(context, config: config);
    });
```

(`import 'package:clando/services/remote_config_service.dart';`).

- [ ] **Step 6 : `checkForAppUpdate(context, {RemoteConfig? config})`** — dans `app_update_service.dart`, remplacer le début de la fonction par :

```dart
Future<void> checkForAppUpdate(BuildContext context, {RemoteConfig? config}) async {
  try {
    int versionCodeServeur;
    int minimumServeur;
    String? urlTelechargement;
    if (config != null) {
      // Une seule requête au démarrage : /v2/config porte déjà la version.
      versionCodeServeur = config.version.code;
      minimumServeur = config.version.minCode;
      urlTelechargement = config.version.downloadUrl;
    } else {
      final response = await http
          .get(Uri.parse('https://pouletafc.com/api/v1.0/getAppVersion'))
          .timeout(const Duration(seconds: 6));
      final json = jsonDecode(response.body);
      if (json['response'] != 200) return;
      final data = json['data'] as Map;
      versionCodeServeur = (data['version_code'] as num?)?.toInt() ?? 0;
      minimumServeur = (data['min_version_code'] as num?)?.toInt() ?? 0;
      urlTelechargement = data['download_url']?.toString();
    }
    if (urlTelechargement == null || urlTelechargement.isEmpty) return;
    // … suite inchangée (PackageInfo, decisionMiseAJour, dialogue)
```

- [ ] **Step 7 : panier** — `cart_screen.dart` :
  1. `final pouletAfcLocation = {...}` → `static const Map<String, double> _pointDeRetraitParDefaut = {'lat': 9.298160757436905, 'lon': 13.399066915343388}; Map<String, double> get pouletAfcLocation { final p = RemoteConfigService.instance.config?.pointDeRetrait; return p == null ? _pointDeRetraitParDefaut : {'lat': p.lat, 'lon': p.lon}; }` avec commentaire : « Le point de retrait vient du serveur (/v2/config) ; les coordonnées historiques ne servent que si la configuration n'a jamais pu être chargée. »
  2. `_estimationLocaleDesFrais()` supprimée. Dans `updateDeliveryPrice()`, la branche `!isConnected || deliveryDistance <= 0` devient : `setState(() { fees = 0.0; _fraisAConfirmer = isDeliverySelected; });` et la branche `on DevisException` idem. Nouveau champ `bool _fraisAConfirmer = false;` remis à `false` quand un devis serveur arrive ou quand la livraison est désélectionnée. Dans `_buildBillingSection`, la ligne `'Prix de livraison: ...'` affiche `'Prix de livraison : calculé à l\'envoi'` quand `_fraisAConfirmer` (le serveur fixe les frais à partir de `distance_km`).
  3. `details` du `PaymentOptionsScreen` (les deux occurrences, lignes ~436 et ~1152) : ajouter `'distance_km': deliveryDistance, 'delivery_lat': lat, 'delivery_lon': lon` où `lat`/`lon` sont lus juste avant via `await SessionManager().get("lat")` / `"lon"` (les coordonnées choisies dans `take_position_cart.dart`), convertis en `double?` par `double.tryParse('$x')`.
  4. Retirer `fetchDeliveryParameters()`/`_loadCachedDeliveryParameters()`/`commandKilometerPrice`/`minPriceCommand` et `_deliveryParamsSettled` (mettre `_isPriceReady => _deliveryDistanceSettled`) : plus aucun consommateur de `getParameters` dans l'app. Vérifier par `grep -n "getParameters\|minPriceCommand\|commandKilometerPrice" lib/screens/cart_screen/cart_screen.dart` → vide.

- [ ] **Step 8 : envoi à `validerPanier`** — `payment_options_screen.dart`, dans les deux `body: {...}` (lignes ~249 et ~334), ajouter :

```dart
              "distance_km": "${widget.details['distance_km'] ?? ''}",
              "delivery_lat": "${widget.details['delivery_lat'] ?? ''}",
              "delivery_lon": "${widget.details['delivery_lon'] ?? ''}",
```

et dans la construction du `QueuedOrder` (ligne ~300) : `distanceKm: "${widget.details['distance_km'] ?? ''}", deliveryLat: "${widget.details['delivery_lat'] ?? ''}", deliveryLon: "${widget.details['delivery_lon'] ?? ''}",`.

`order_queue_service.dart` : `QueuedOrder` gagne `final String distanceKm; final String deliveryLat; final String deliveryLon;` (constructeur : `this.distanceKm = '', this.deliveryLat = '', this.deliveryLon = ''`), `toJson` les écrit, `fromJson` les lit avec `?? ''` (file déjà persistée sans ces clés), `_processOrder` les envoie (`"distance_km": order.distanceKm, "delivery_lat": order.deliveryLat, "delivery_lon": order.deliveryLon`). Le serveur ignore une chaîne vide (`is_numeric('')` faux → chemin legacy ; `PointDeLivraison::nombre('')` → null → source suivante).

- [ ] **Step 9 : drapeau coursier** — `home_screen.dart:~1130` : entourer le widget de l'entrée Coursier de `if (RemoteConfigService.instance.fonction('coursier')) ...` (lire le widget parent exact avant d'éditer : c'est un élément de liste/grille, utiliser la syntaxe `if` de collection). `destinationResearch.dart:_openCoursier` : premier `if (!RemoteConfigService.instance.fonction('coursier')) { ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Le service coursier est momentanément indisponible.'))); return; }` et masquer le bouton qui l'appelle de la même manière que sur l'accueil.

- [ ] **Step 10 : vérification** — `flutter analyze` (0 nouvelle erreur : les 3 préexistantes `background_service.dart:162,165`, `historique_screen.dart:108` restent), `flutter test` (tout vert), `grep -rn "getParameters" lib/` → vide, `git status --short --branch`.

---

### Task 5 : `pouletafc_agent` — `RemoteConfigService` (`app=agent`) et version via la config

**Files:**
- Create: `lib/services/remote_config_service.dart`, `test/remote_config_service_test.dart`
- Modify: `lib/services/app_update_service.dart`, `lib/screens/home_screen/home_screen.dart:157-160`

**Interfaces:** identiques à Task 4, package `afc_chicken_delivery`, `RemoteConfigService.app = 'agent'`, `kApiBaseV2` défini localement en tête du fichier (`const String kApiBaseV2 = 'https://pouletafc.com/api/v2';` — l'app agent n'a pas `tarification_service.dart`).

- [ ] **Step 1 :** copier le test de Task 4 en remplaçant `package:clando/` par `package:afc_chicken_delivery/`, `'app': 'client'` par `'app': 'agent'`, et l'URL attendue par `https://pouletafc.com/api/v2/config?app=agent`. `flutter test test/remote_config_service_test.dart` → échec de compilation.
- [ ] **Step 2 :** copier `remote_config_service.dart` de Task 4 : remplacer l'import de `kApiBaseV2` par la constante locale, `static const String app = 'agent'`. Test → 6 passed.
- [ ] **Step 3 :** `app_update_service.dart` : même modification que Task 4 Step 6, avec l'URL `getAppVersion?app=agent` dans le repli. `home_screen.dart` :

```dart
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final config = await RemoteConfigService.instance.charger();
      if (mounted) checkForAppUpdate(context, config: config);
      if (mounted) _ouvrirDemandesDepuisNotificationSiBesoin();
    });
```

- [ ] **Step 4 :** `flutter analyze` (0 erreur), `flutter test` (tout vert), `git status --short --branch`.

---

### Task 6 : documentation

- [ ] `ARCHITECTURE.md` : §19 « Tranche 2 » (frais serveur §0.1 avec les 4 sources `retrait|client|estimation_serveur|legacy`, détours §0.2 et le changement de comportement VIP/arrondi, `/v2/config` §0.3 et la liste des variables `.env`, apps §0.4, hors périmètre §0.5, tests : 5 + 4 + 4 serveur, 6 + 6 apps).
- [ ] `CLAUDE.md` : compléter la règle 23 — « `validerPanier`/`createOrder` passent par `App\Support\FraisDeLivraison` ; le point de retrait est `PointDeLivraison::pointDeRetrait()`, exposé par `/v2/config` — ne jamais recoder ses coordonnées dans une app » ; nouvelle règle 24 — « Toute fonctionnalité qu'on veut pouvoir couper à distance se déclare dans `config/mobile_app.php` `fonctionnalites` (+ `.env.example`) et se lit via `RemoteConfigService.instance.fonction('nom')` dans les apps, jamais par une constante locale. Les deux fichiers `remote_config_service.dart` (user/agent) sont identiques au package près : les modifier ensemble. »
- [ ] `TASKS.md` : passer la ligne « Tranche 2 » à « terminé, non commité ». `tache.md` : section de clôture avec les points à vérifier par Codex (facteur route 1,25 ; estimation quand distance < 95 % du vol d'oiseau ; drapeaux depuis `.env` en attendant la base).
- [ ] Grep secrets : `set -a && . ./.env && set +a` puis `grep -rlF "$DASHBOARD_ADMIN_PASSWORD"` sur les docs et fichiers touchés → vide.

---

## Auto-revue

**Couverture du spec.** §0.1 → Task 1 (calcul, `pointDeRetrait()`, deux chemins) ; §0.2 → Task 2 ; §0.3 → Task 3 (contact/fonctionnalites/version/point de retrait, validation `app`) ; §0.4 → Tasks 4-5 (service, démarrage, panier, envoi `distance_km`/coordonnées, file hors-ligne, drapeau coursier, version via config) ; §0.5 documenté en Task 6.

**Arithmétique vérifiée à la main.** Task 1 : 200 F/km × 10 km = 2000 (≥ 400, multiple de 50). Task 2 : 6,1 × 250 = 1525 → 1550 ; VIP 1550 × 1,5 = 2325 → 2350. Task 1 vol d'oiseau Garoua : Δlat 0,0318° ≈ 3,53 km, Δlon 0,0091° × cos(9,3°) ≈ 1,0 km → ≈ 3,67 km ; 1 km < 3,49 (95 %) → estimation 4,59 km ; attendu calculé dans le test par la même fonction `Distance::metres`, pas à la main.

**Placeholders.** Aucun « TBD/TODO » ; les zones d'édition Flutter qui dépendent du widget parent (Task 4 Step 9) donnent la condition exacte et demandent de lire le widget avant d'éditer.

**Cohérence des types.** `pointDeRetrait(): ?array{id,nom,lat,lon}` défini en Task 1, consommé tel quel en Task 1 (`calculer` ne lit que `lat`/`lon`) et Task 3 ; `RemoteConfig.version.minCode`/`downloadUrl` utilisés en Task 4 Step 6 et Task 5 ; `QueuedOrder` champs `distanceKm/deliveryLat/deliveryLon` nommés identiquement dans `payment_options_screen.dart` et `order_queue_service.dart`.
