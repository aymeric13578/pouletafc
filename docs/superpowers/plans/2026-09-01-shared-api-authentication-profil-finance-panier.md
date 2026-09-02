# Authentification API v1.0 — Profil, finance, panier, kiosk, annulation (Plan 3/3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Protéger les derniers endpoints `v1.0` non couverts par les plans 1/2 : profil et suivi de service (`UserController`), finances agent (`FinanceController`), panier client (`CartController`), déverrouillage de kiosk (`KioskLockController`), création boutique/produit (`ProductsController`/`ShopsController`), et annulation client/agent (`ClandoController::declinCommand`, `OrderController::declinOrderCommand`).

**Architecture:** Même fondation que les plans 1/2 (`App\Support\ApiAuthentification::utilisateurOuErreur()`/`estStaff()`). La grande majorité des endpoints de ce plan sont **auto-référentiels** (l'appelant agit sur son propre compte) : le correctif consiste à dériver l'identité du jeton et à agir directement sur `$utilisateur` (le modèle déjà chargé) plutôt que de re-résoudre un `ref`/`id_user` fourni par le client. Trois endpoits sortent de ce schéma : le panier (propriété d'une ressource tierce, `Cart`/`CartItem`), le kiosk (double jeton — jeton de session Sanctum + jeton de déverrouillage kiosk, deux mécanismes distincts qui coexistent), et `declinCommand` (double usage légitime : le client annule sa propre course, OU un agent décline une offre pas encore prise — deux appelants différents sur la même route).

**Tech Stack:** Laravel, Sanctum (`App\Support\ApiAuthentification`, Plans 1/2), PHPUnit (`tests/Feature`, pas de `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-09-01-shared-api-authentication-design.md` (§6.4, §6.5)

## Global Constraints

- Le jeton est transmis en paramètre de requête (`token` par défaut, sauf collision de nom — voir Tâche 6), jamais en en-tête `Authorization` — utiliser `app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request)`.
- Enveloppe JSON partout : `{"response": <code>, "message": "..."}`. `401` = pas de jeton valide. `403` = jeton valide mais pas propriétaire de la ressource ciblée.
- `admin`/`employee_afc` (`ApiAuthentification::estStaff()`, déjà construite au Plan 2) contournent une vérification de propriété — seulement là où c'est explicitement indiqué tâche par tâche, jamais par défaut.
- Ce dépôt n'utilise pas `RefreshDatabase`/`DatabaseTransactions` : les tests tournent sur la vraie base configurée, nettoient eux-mêmes ce qu'ils créent en `tearDown()`.
- **Ne jamais tenter de corriger un écart de schéma constaté en local par une migration ou un DDL improvisés** — toujours `Schema::hasColumn()`/`hasTable()` + `$this->markTestSkipped(...)`. Deux incidents réels dans ce projet (Plan 1 Tâche 3, corrigé en 2 rondes) sont partis de cette tentation. `Cart`/`CartItem`/`withdrawal_requests`/`declin_command` n'ont pas été vérifiés colonne par colonne avant l'écriture de ce plan (contrairement aux tables `agents`/`clando`/`order_details`, déjà cartographiées aux plans précédents) — chaque tâche qui les touche doit vérifier `Schema::hasTable()`/`hasColumn()` avant d'écrire son test, pas supposer qu'elles sont à jour.
- **Comparaison de propriété sur `id_agent`** (si une tâche en a besoin) : `order_details.id_agent`/`clando.id_agent` sont des `varchar` sans cast `integer` — toujours `(int) $ressource->id_agent !== $utilisateur->id`, jamais une comparaison stricte nue (leçon du Plan 2, Tâche 2). Vérifier si la même chose s'applique à `clando.id_user`/`order_details.id_user` avant de les comparer directement (probable, mais à confirmer par `Schema::hasColumn` avec le type réel avant d'écrire une comparaison stricte dans le code de production — pas seulement dans le test).
- Tout `Agent::create(...)`/toute ligne de test créée doit être capturée dans un tableau de nettoyage et supprimée en `tearDown()` (leçon du Plan 2, Tâche 3).

---

## Task 1: Profil auto-référentiel — `UserController::updateUser` + `getInfoUser`

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:123-187` (`getInfoUser`)
- Modify: `app/Http/Controllers/API/UserController.php:380-411` (`updateUser`)
- Test: `tests/Feature/AuthProfilTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()` (Plans 1/2).

Les deux endpoints sont appelés partout (`plouletafcapp`, `pouletafc_agent`, `empolyeeafc`) avec le `ref` de l'appelant lui-même (`SessionManager().get("ref")`), jamais celui d'un tiers — confirmé par grep sur les 3 apps avant l'écriture de ce plan. Le correctif remplace la résolution par `ref` (fournie par le client, donc falsifiable) par l'utilisateur déjà résolu depuis le jeton.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthProfilTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthProfilTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerAgent(): User
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;

        return $agent;
    }

    public function test_getInfoUser_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getInfoUser?ref=peu-importe')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getInfoUser_ignore_le_ref_du_client_et_renvoie_le_bon_compte(): void
    {
        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $reponse = $this->getJson('/api/v1.0/getInfoUser?token=' . $jeton . '&ref=' . $victime->ref);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $data = $reponse->json('data');
        $this->assertCount(1, $data, 'getInfoUser ne doit renvoyer que le compte authentifié, jamais celui visé par le ref du client.');
        $this->assertSame($agent->ref, $data[0]['ref']);
    }

    public function test_updateUser_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateUser', ['ref' => 'peu-importe', 'name' => 'X'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updateUser_modifie_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateUser', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'name' => 'Nom modifié',
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertSame('Nom modifié', $agent->name, "Le nom de l'appelant authentifié doit changer.");
        $this->assertNotSame('Nom modifié', $victime->name, "Le compte visé par le ref du client ne doit jamais être modifié.");
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthProfilTest`
Expected: FAIL — `test_getInfoUser_sans_jeton_c_est_401` et `test_updateUser_sans_jeton_c_est_401` reçoivent une autre réponse que `401` (aucune authentification actuellement) ; les deux autres échouent car le `ref` du client fait toujours foi aujourd'hui.

- [ ] **Step 3: Modifier `getInfoUser`**

Dans `app/Http/Controllers/API/UserController.php`, remplacer :

```php
    public function getInfoUser(Request $request)
    {
        $ref = $request->input('ref');
```

par :

```php
    public function getInfoUser(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $ref = $utilisateur->ref;
```

(le reste de la méthode, qui utilise déjà `$ref` pour la requête `User::where('ref', $ref)->with([...])`, reste identique — `$ref` vient maintenant du compte authentifié, plus jamais du client.)

- [ ] **Step 4: Modifier `updateUser`**

Remplacer :

```php
    public function updateUser(Request $request)
    {
        $ref = $request->input('ref');
        $seachUser = User::where('ref', $ref)->first();

        if (!$seachUser) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant"
            ]);
        }

        $update = User::where('ref', $ref)->update([
            'name' => $request->input('name', $seachUser->name),
            'last_name' => $request->input('lastname', $seachUser->last_name),
            'whatsapp' => $request->input('whatsapp', $seachUser->whatsapp),
            'phone' => $request->input('phone', $seachUser->phone),
            'city' => $request->input('city', $seachUser->city),
        ]);

        if ($update) {
```

par :

```php
    public function updateUser(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $update = $utilisateur->update([
            'name' => $request->input('name', $utilisateur->name),
            'last_name' => $request->input('lastname', $utilisateur->last_name),
            'whatsapp' => $request->input('whatsapp', $utilisateur->whatsapp),
            'phone' => $request->input('phone', $utilisateur->phone),
            'city' => $request->input('city', $utilisateur->city),
        ]);

        if ($update) {
```

(le `$seachUser`/`User::where('ref', $ref)` initial disparaît entièrement, remplacé par `$utilisateur` déjà résolu — plus besoin de re-interroger la base.)

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthProfilTest`
Expected: PASS (4 tests) — la table `users` est entièrement à jour localement (confirmé aux plans précédents), aucun skip attendu.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/AuthProfilTest.php
git commit -m "feat: getInfoUser/updateUser exigent un jeton, ignorent le ref du client"
```

---

## Task 2: Suppression de compte — `UserController::deleteUser`

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:413-457`
- Test: `tests/Feature/AuthProfilTest.php` (ajoute des tests)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`.

Cette méthode a déjà une confirmation par mot de passe — la protection ajoutée ici empêche un tiers de faire supprimer le compte de quelqu'un d'autre en devinant son `ref`, même sans connaître son mot de passe (aujourd'hui, sans jeton, un tiers peut au moins provoquer une tentative — inoffensive sans le mot de passe, mais la vérification de mot de passe seule n'est pas la bonne défense contre ça).

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/AuthProfilTest.php` :

```php
    public function test_deleteUser_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/deleteUser', ['ref' => 'peu-importe', 'password' => 'password'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_deleteUser_supprime_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        $victime = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/deleteUser', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseMissing('users', ['id' => $agent->id]);
        $this->assertDatabaseHas('users', ['id' => $victime->id]);

        $victime->delete();
    }
```

Note : `$agent` n'a pas besoin d'être ajouté à `$this->utilisateursCrees` avant cet appel puisqu'il est déjà l'appelant supprimé par le test lui-même — s'il ne l'était pas (assertion échouée avant), le nettoyage de `tearDown()` sur une ligne déjà absente reste un no-op sûr (voir Plan 1, Tâche 6).

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthProfilTest`
Expected: FAIL sur `test_deleteUser_sans_jeton_c_est_401` (aucune authentification aujourd'hui) ; le second peut déjà passer par coïncidence (le `ref` du client correspond à un vrai compte avec le bon mot de passe) — le premier échec suffit à prouver le manque.

- [ ] **Step 3: Modifier `deleteUser`**

Remplacer :

```php
    public function deleteUser(Request $request)
    {
        $ref = $request->input('ref');
        $password = $request->input('password');

        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $seachUser = User::where('ref', $ref)->first();

        if (!$seachUser) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant"
            ]);
        }

        // Confirmation par mot de passe avant suppression définitive
        if (!$password || !Hash::check($password, $seachUser->password)) {
            return response()->json([
                "response" => 400,
                "message" => "Mot de passe incorrect"
            ]);
        }

        try {
            $seachUser->tokens()->delete();
            $seachUser->purgeAccount();
        } catch (\Throwable $e) {
            Log::error("Échec suppression compte (ref {$ref}) : " . $e->getMessage());
```

par :

```php
    public function deleteUser(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $password = $request->input('password');

        // Confirmation par mot de passe avant suppression définitive
        if (!$password || !Hash::check($password, $utilisateur->password)) {
            return response()->json([
                "response" => 400,
                "message" => "Mot de passe incorrect"
            ]);
        }

        try {
            $utilisateur->tokens()->delete();
            $utilisateur->purgeAccount();
        } catch (\Throwable $e) {
            Log::error("Échec suppression compte (ref {$utilisateur->ref}) : " . $e->getMessage());
```

(le reste de la méthode, après le `catch`, reste identique.)

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthProfilTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/AuthProfilTest.php
git commit -m "feat: deleteUser exige un jeton, agit sur l'appelant authentifié"
```

---

## Task 3: Suivi de service auto-référentiel — `takeDay` + `takeDayDesactive` + `updateDeliveryPosition`

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:20-84` (`takeDay`, `updateDeliveryPosition`)
- Modify: `app/Http/Controllers/API/UserController.php:86-121` (`takeDayDesactive`)
- Test: `tests/Feature/AuthSuiviServiceTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`.

Trois endpoints auto-référentiels : `takeDay`/`takeDayDesactive` marquent le début/fin de service (identité actuellement lue via `ref`), `updateDeliveryPosition` écrit `latitude`/`longitude` sur `users` (identité actuellement lue via `id_user`, même faille qu'`updateAgentPosition` corrigée au Plan 1 — gap découvert en préparant ce plan, ajouté par décision explicite de l'utilisateur).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthSuiviServiceTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthSuiviServiceTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerAgent(): User
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;

        return $agent;
    }

    public function test_takeDay_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/takeDay', ['ref' => 'peu-importe', 'lat' => 9.3, 'lon' => 13.4])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_takeDay_active_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/takeDay', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertSame(1, (int) $agent->in_activity);
        $this->assertNotSame(1, (int) $victime->in_activity);
    }

    public function test_takeDayDesactive_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/takeDayDesactive', ['ref' => 'peu-importe'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updateDeliveryPosition_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateDeliveryPosition', ['id_user' => 999999, 'lat' => 9.3, 'lon' => 13.4])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updateDeliveryPosition_ignore_le_id_user_du_client(): void
    {
        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateDeliveryPosition', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $agent->latitude, 0.0001);
        $this->assertNull($victime->latitude);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthSuiviServiceTest`
Expected: FAIL sur les trois tests `sans_jeton` (aucune authentification aujourd'hui) et sur les deux tests d'usurpation (le `ref`/`id_user` du client fait toujours foi).

- [ ] **Step 3: Modifier `takeDay`**

Remplacer :

```php
    public function takeDay(Request $request)
    {
        $ref = $request->input('ref');
        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $data = User::where('ref', $ref)->first();
        if (!$data) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant",
            ]);
        }

        User::where('ref', $ref)->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $data->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "beginDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
            "data" => $data->fresh()
        ]);
    }
```

par :

```php
    public function takeDay(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $utilisateur->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $utilisateur->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "beginDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
            "data" => $utilisateur->fresh()
        ]);
    }
```

- [ ] **Step 4: Modifier `updateDeliveryPosition`**

Remplacer :

```php
    public function updateDeliveryPosition(Request $request)
    {
        $userId = $request->input('id_user');
        if (!$userId) {
            return response()->json([
                "response" => 400,
                "message" => "Identifiant utilisateur manquant",
            ]);
        }

        $updated = User::where('id', $userId)->update([
            'longitude' => $request->input('lon'),
            'latitude' => $request->input('lat'),
        ]);

        if ($updated) {
```

par :

```php
    public function updateDeliveryPosition(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $updated = $utilisateur->update([
            'longitude' => $request->input('lon'),
            'latitude' => $request->input('lat'),
        ]);

        if ($updated) {
```

- [ ] **Step 5: Modifier `takeDayDesactive`**

Remplacer :

```php
    public function takeDayDesactive(Request $request)
    {
        $ref = $request->input('ref');
        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $data = User::where('ref', $ref)->first();
        if (!$data) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant",
            ]);
        }

        User::where('ref', $ref)->update([
            'in_activity' => 0,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $data->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "endDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
        ]);
    }
```

par :

```php
    public function takeDayDesactive(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $utilisateur->update([
            'in_activity' => 0,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $utilisateur->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "endDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
        ]);
    }
```

- [ ] **Step 6: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthSuiviServiceTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/AuthSuiviServiceTest.php
git commit -m "feat: takeDay/takeDayDesactive/updateDeliveryPosition exigent un jeton"
```

---

## Task 4: Finances agent — `FinanceController` (4 méthodes)

**Files:**
- Modify: `app/Http/Controllers/API/FinanceController.php` (`requestWithdrawal`, `getWithdrawalStatus`, `getPaymentsAgent`, `getfinanceAgent`)
- Test: `tests/Feature/AuthFinanceAgentTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`.

Les 4 méthodes sont auto-référentielles (l'agent consulte/agit sur ses propres finances) — remplacer `$request->id_user` par `$utilisateur->id` partout où il désigne l'appelant. **Ne pas toucher** au champ `numero` de `requestWithdrawal` (déjà audité et documenté comme volontairement indépendant du numéro de l'agent — voir `ARCHITECTURE.md` §12, "il n'est pas forcément celui de l'agent") : cette tâche sécurise uniquement *qui peut appeler* l'endpoint, pas les règles métier déjà en place sur son contenu.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthFinanceAgentTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthFinanceAgentTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerAgent(): User
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;

        return $agent;
    }

    public function test_getfinanceAgent_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getfinanceAgent')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getPaymentsAgent_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getPaymentsAgent')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getWithdrawalStatus_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getWithdrawalStatus')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_requestWithdrawal_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', ['montant' => 1000, 'mode' => 'cash'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_requestWithdrawal_utilise_l_appelant_authentifie_pas_le_id_user_du_client(): void
    {
        if (! Schema::hasTable('credit_agents') || ! Schema::hasTable('withdrawal_requests')) {
            $this->markTestSkipped('Tables credit_agents/withdrawal_requests absentes de cette base locale.');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        \Illuminate\Support\Facades\DB::table('credit_agents')->insert([
            'id_agent' => $agent->id,
            'amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(\App\Support\LivreDeComptes::class)->reportOuverture('agent', $agent->id, 10000);

        $this->postJson('/api/v1.0/requestWithdrawal', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'montant' => 1000,
            'mode' => 'cash',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('withdrawal_requests', ['id_agent' => $agent->id, 'amount' => 1000]);
        $this->assertDatabaseMissing('withdrawal_requests', ['id_agent' => $victime->id]);

        \App\Models\WithdrawalRequest::where('id_agent', $agent->id)->delete();
        \Illuminate\Support\Facades\DB::table('credit_agents')->where('id_agent', $agent->id)->delete();
        if (Schema::hasTable('mouvements_financiers')) {
            \App\Models\MouvementFinancier::where('acteur_type', 'agent')->where('acteur_id', $agent->id)->delete();
        }
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthFinanceAgentTest`
Expected: FAIL sur les 4 tests `sans_jeton` (aucune authentification aujourd'hui) ; le 5e skip ou échoue selon le schéma local (les tables financières manquent probablement, voir Global Constraints — un skip ici n'est pas un échec de ce plan).

- [ ] **Step 3: Modifier `requestWithdrawal`**

Dans `app/Http/Controllers/API/FinanceController.php`, remplacer :

```php
    public function requestWithdrawal(Request $request)
    {
        $idAgent = $request->id_user;
```

par :

```php
    public function requestWithdrawal(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $idAgent = $utilisateur->id;
```

(le reste de la méthode utilise déjà `$idAgent`, inchangé.)

- [ ] **Step 4: Modifier `getWithdrawalStatus`**

Remplacer :

```php
    public function getWithdrawalStatus(Request $request)
    {
        $demande = WithdrawalRequest::where('acteur_type', 'agent')->where('id_agent', $request->id_user)
            ->where('status', 'pending')
            ->first();

        return response()->json(['response' => 200, 'data' => $demande]);
    }
```

par :

```php
    public function getWithdrawalStatus(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $demande = WithdrawalRequest::where('acteur_type', 'agent')->where('id_agent', $utilisateur->id)
            ->where('status', 'pending')
            ->first();

        return response()->json(['response' => 200, 'data' => $demande]);
    }
```

- [ ] **Step 5: Modifier `getPaymentsAgent`**

Remplacer :

```php
    public function getPaymentsAgent(Request $request)
    {
        $idAgent = $request->id_user;
```

par :

```php
    public function getPaymentsAgent(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $idAgent = $utilisateur->id;
```

- [ ] **Step 6: Modifier `getfinanceAgent`**

Remplacer :

```php
    public function getfinanceAgent(Request $request)
    {
        
        
         $solde =(new Fonction())->solde($request->id_user);
```

par :

```php
    public function getfinanceAgent(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $solde =(new Fonction())->solde($utilisateur->id);
```

Puis, plus bas dans la même méthode, chaque usage restant de `$request->id_user` devient `$utilisateur->id` :

```php
        $historiqueClando = DB::table('clando')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCommand = DB::table('order_details')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCredit = DB::table('credit_agents')->where('id_agent',$request->id_user)->get();
        $historiquedeposit = DB::table('deposits')->where('id_agent',$request->id_user)->where('status','Success')->get();
```

devient :

```php
        $historiqueClando = DB::table('clando')->where('id_agent',$utilisateur->id)->where('status','Success')->get();
        $historiqueCommand = DB::table('order_details')->where('id_agent',$utilisateur->id)->where('status','Success')->get();
        $historiqueCredit = DB::table('credit_agents')->where('id_agent',$utilisateur->id)->get();
        $historiquedeposit = DB::table('deposits')->where('id_agent',$utilisateur->id)->where('status','Success')->get();
```

et :

```php
        $retraitEnAttente = WithdrawalRequest::where('acteur_type', 'agent')
            ->where('id_agent', $request->id_user)
            ->where('status', 'pending')
            ->first();
```

devient :

```php
        $retraitEnAttente = WithdrawalRequest::where('acteur_type', 'agent')
            ->where('id_agent', $utilisateur->id)
            ->where('status', 'pending')
            ->first();
```

et :

```php
        $depotRecu = (float) (\App\Models\Agent::where('id_user', $request->id_user)->value('deposit_recu') ?? 0);
```

devient :

```php
        $depotRecu = (float) (\App\Models\Agent::where('id_user', $utilisateur->id)->value('deposit_recu') ?? 0);
```

et enfin :

```php
        $mouvements = \App\Models\MouvementFinancier::where('acteur_type', \App\Models\MouvementFinancier::ACTEUR_AGENT)
            ->where('acteur_id', $request->id_user)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['sens', 'type', 'montant', 'libelle', 'created_at']);
```

devient :

```php
        $mouvements = \App\Models\MouvementFinancier::where('acteur_type', \App\Models\MouvementFinancier::ACTEUR_AGENT)
            ->where('acteur_id', $utilisateur->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['sens', 'type', 'montant', 'libelle', 'created_at']);
```

- [ ] **Step 7: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthFinanceAgentTest`
Expected: PASS (4 tests confirmés), 5e test PASS ou SKIP selon le schéma local.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/API/FinanceController.php tests/Feature/AuthFinanceAgentTest.php
git commit -m "feat: FinanceController exige un jeton, agit sur l'appelant authentifié"
```

---

## Task 5: Panier client — `CartController` (`deleteCart`, `deleteProductCart`, `updateItem`)

**Files:**
- Modify: `app/Http/Controllers/API/CartController.php:165-185` (`deleteCart`, `deleteProductCart`)
- Modify: `app/Http/Controllers/API/CartController.php:241-248` (`updateItem`)
- Test: `tests/Feature/AuthPanierTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`. Pas de `estStaff()` ici — c'est le panier d'un client, jamais géré par `employee_afc`/`admin` depuis les apps mobiles.

Les trois méthodes acceptent aujourd'hui un `id` numérique brut sans vérifier que le panier/article appartient à l'appelant (`Cart.user_id`/`CartItem.user_id`, confirmés dans `addToCartAndView` un peu plus haut dans ce même fichier).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthPanierTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPanierTest extends TestCase
{
    private array $utilisateursCrees = [];
    private ?Cart $panier = null;
    private ?CartItem $article = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('carts', 'user_id') || ! Schema::hasColumn('cart_items', 'user_id')) {
            $this->markTestSkipped('Colonne user_id absente de carts/cart_items sur cette base locale.');
        }
    }

    protected function tearDown(): void
    {
        $this->article?->delete();
        $this->panier?->delete();
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerClient(): User
    {
        $client = User::factory()->create(['role' => 'user', 'status' => 'Success']);
        $this->utilisateursCrees[] = $client;

        return $client;
    }

    public function test_deleteCart_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/DeleteCart?id=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_deleteCart_avec_le_jeton_d_un_autre_client_c_est_403(): void
    {
        $proprietaire = $this->creerClient();
        $intrus = $this->creerClient();

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);

        $jeton = $intrus->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/DeleteCart?token=' . $jeton . '&id=' . $this->panier->id)
            ->assertOk()->assertJsonPath('response', 403);

        $this->panier->refresh();
        $this->assertSame('pending', $this->panier->status);
    }

    public function test_deleteCart_avec_le_bon_client_fonctionne(): void
    {
        $proprietaire = $this->creerClient();

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);

        $jeton = $proprietaire->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/DeleteCart?token=' . $jeton . '&id=' . $this->panier->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->panier->refresh();
        $this->assertSame('failed', $this->panier->status);
    }

    public function test_updateItem_avec_le_jeton_d_un_autre_client_c_est_403(): void
    {
        $proprietaire = $this->creerClient();
        $intrus = $this->creerClient();
        $produit = Product::first();
        if (! $produit) {
            $this->markTestSkipped('Aucun produit en base pour ce test.');
        }

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);
        $this->article = CartItem::create([
            'user_id' => $proprietaire->id,
            'product_id' => $produit->id,
            'cart_id' => $this->panier->id,
            'quantity' => 1,
            'amount' => $produit->price,
            'status' => 'Success',
        ]);

        $jeton = $intrus->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/updateItem?token=' . $jeton . '&id=' . $this->article->id . '&quantity=5')
            ->assertOk()->assertJsonPath('response', 403);

        $this->article->refresh();
        $this->assertSame(1, (int) $this->article->quantity);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthPanierTest`
Expected: FAIL — `test_deleteCart_sans_jeton_c_est_401` reçoit `response: 404` (aucune authentification aujourd'hui, l'`id` `999999` n'existe simplement pas) ; les tests de propriété échouent (`response: 200` reçu à la place de `403`, aucune vérification aujourd'hui) ; sauf skip global si `carts`/`cart_items` n'ont pas `user_id` sur cette base locale.

- [ ] **Step 3: Modifier `deleteCart`**

Dans `app/Http/Controllers/API/CartController.php`, remplacer :

```php
    public function deleteCart(Request $request)
    { 

        $carts = Cart::where('id', $request->id)->update([
            'status'=>'failed'
        ]);

        if($carts) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);

    }
```

par :

```php
    public function deleteCart(Request $request)
    { 
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $panier = Cart::where('id', $request->id)->first();

        if (! $panier) {
            return response()->json(['response' => 404]);
        }

        if ($panier->user_id !== $utilisateur->id) {
            return response()->json(['response' => 403, 'message' => "Ce panier ne vous appartient pas."]);
        }

        $panier->update(['status' => 'failed']);

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 4: Modifier `deleteProductCart`**

Remplacer :

```php
    public function deleteProductCart(Request $request)
    {
        $cartItems = CartItem::where('id', $request->id)->update([
            'status'=>'failed'
        ]);


        if($cartItems) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);
    }
```

par :

```php
    public function deleteProductCart(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $article = CartItem::where('id', $request->id)->first();

        if (! $article) {
            return response()->json(['response' => 404]);
        }

        if ($article->user_id !== $utilisateur->id) {
            return response()->json(['response' => 403, 'message' => "Cet article ne vous appartient pas."]);
        }

        $article->update(['status' => 'failed']);

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 5: Modifier `updateItem`**

Remplacer :

```php
    public function updateItem(Request $request)
    {
        
        $cartItems = CartItem::where('id', $request->id)->update([
            'quantity'=> $request->quantity
        ]);
         return response()->json(['response' => 200, ]);
    }
```

par :

```php
    public function updateItem(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $article = CartItem::where('id', $request->id)->first();

        if (! $article) {
            return response()->json(['response' => 404]);
        }

        if ($article->user_id !== $utilisateur->id) {
            return response()->json(['response' => 403, 'message' => "Cet article ne vous appartient pas."]);
        }

        $article->update(['quantity' => $request->quantity]);

        return response()->json(['response' => 200]);
    }
```

Note : `user_id` sur `carts`/`cart_items` (contrairement à `id_agent` sur `clando`/`order_details`) n'a pas été vérifié colonne-par-colonne avant l'écriture de ce plan — si `Schema::hasColumn('carts', 'user_id')` révèle un type non entier lors de l'exécution de cette tâche, appliquer la même leçon que le Plan 2 (`(int) $panier->user_id !== $utilisateur->id`) avant de committer, plutôt que de découvrir le problème en revue.

- [ ] **Step 6: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthPanierTest`
Expected: PASS (4 tests) ou skip global si `carts`/`cart_items` n'ont pas `user_id` localement.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/API/CartController.php tests/Feature/AuthPanierTest.php
git commit -m "feat: deleteCart/deleteProductCart/updateItem exigent un jeton et vérifient la propriété"
```

---

## Task 6: Déverrouillage de kiosk — `KioskLockController::deverrouiller`

**Files:**
- Modify: `app/Http/Controllers/API/KioskLockController.php`
- Test: `tests/Feature/AuthKioskLockTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur(Request, string $champ = 'token')` — appelé ici avec `$champ = 'session_token'`, **jamais** `'token'` (déjà utilisé par le jeton de déverrouillage kiosk lui-même, `KioskUnlockToken` — collision explicitement anticipée par le Plan 1, voir `ApiAuthentification`'s docblock). `ApiAuthentification::estStaff()`.

Cet endpoint mélange deux jetons distincts qui doivent rester séparés : le jeton kiosk (`token`, prouve qu'un QR affiché à l'écran a été scanné) et désormais un jeton de session Sanctum (`session_token`, prouve que le scanneur est bien un compte `employee_afc`/`admin` connecté). Les deux sont requis.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthKioskLockTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\KioskUnlockToken;
use App\Models\User;
use Tests\TestCase;

class AuthKioskLockTest extends TestCase
{
    private array $utilisateursCrees = [];
    private ?KioskUnlockToken $jetonKiosk = null;

    protected function tearDown(): void
    {
        $this->jetonKiosk?->delete();
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerJetonKiosk(): KioskUnlockToken
    {
        $this->jetonKiosk = KioskUnlockToken::create([
            'token' => 'TEST-KIOSK-' . uniqid(),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $this->jetonKiosk;
    }

    private function creerEmploye(): User
    {
        $employe = User::factory()->create(['role' => 'employee_afc', 'status' => 'Success']);
        $this->utilisateursCrees[] = $employe;

        return $employe;
    }

    public function test_deverrouiller_sans_jeton_de_session_c_est_401(): void
    {
        $jeton = $this->creerJetonKiosk();

        $this->postJson('/api/v1.0/deverrouillerEcranKiosk', [
            'token' => $jeton->token,
            'id_user' => 1,
        ])->assertOk()->assertJsonPath('response', 401);

        $jeton->refresh();
        $this->assertNull($jeton->unlocked_at, "Sans jeton de session valide, l'écran ne doit pas être déverrouillé.");
    }

    public function test_deverrouiller_avec_un_compte_client_c_est_403(): void
    {
        $jeton = $this->creerJetonKiosk();
        $client = User::factory()->create(['role' => 'user', 'status' => 'Success']);
        $this->utilisateursCrees[] = $client;
        $sessionToken = $client->createToken('client-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/deverrouillerEcranKiosk', [
            'token' => $jeton->token,
            'session_token' => $sessionToken,
            'id_user' => $client->id,
        ])->assertOk()->assertJsonPath('response', 403);

        $jeton->refresh();
        $this->assertNull($jeton->unlocked_at);
    }

    public function test_deverrouiller_avec_un_employe_fonctionne(): void
    {
        $jeton = $this->creerJetonKiosk();
        $employe = $this->creerEmploye();
        $sessionToken = $employe->createToken('employee-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/deverrouillerEcranKiosk', [
            'token' => $jeton->token,
            'session_token' => $sessionToken,
            'id_user' => $employe->id,
        ])->assertOk()->assertJsonPath('response', 200);

        $jeton->refresh();
        $this->assertNotNull($jeton->unlocked_at);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthKioskLockTest`
Expected: FAIL sur les 3 tests — `deverrouiller` accepte aujourd'hui n'importe quel `id_user` sans vérifier de session.

- [ ] **Step 3: Modifier `deverrouiller`**

Dans `app/Http/Controllers/API/KioskLockController.php`, remplacer :

```php
    public function deverrouiller(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'token' => ['required', 'string'],
            'id_user' => ['required', 'integer'],
        ]);

        $jeton = KioskUnlockToken::where('token', $valide['token'])->first();
```

par :

```php
    public function deverrouiller(Request $request): JsonResponse
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request, 'session_token');
        if ($utilisateur instanceof JsonResponse) {
            return $utilisateur;
        }

        if (! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Seuls un employé ou un administrateur peuvent déverrouiller un écran."]);
        }

        $valide = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $jeton = KioskUnlockToken::where('token', $valide['token'])->first();
```

Puis, plus bas, l'écriture de `unlocked_by_user_id` (qui lisait `$valide['id_user']`, désormais retiré de la validation) devient :

```php
        $jeton->update([
            'unlocked_at' => now(),
            'unlocked_by_user_id' => $valide['id_user'],
```

remplacé par :

```php
        $jeton->update([
            'unlocked_at' => now(),
            'unlocked_by_user_id' => $utilisateur->id,
```

(le reste du tableau passé à `update()` — le commentaire sur `expires_at` et sa valeur — reste identique.)

- [ ] **Step 4: Mettre à jour le docblock de la classe**

Le commentaire en tête de fichier (`app/Http/Controllers/API/KioskLockController.php:10-15`) affirme encore que `id_user` n'est pas vérifié — devenu faux avec ce correctif. Remplacer :

```php
/**
 * Déverrouillage d'un écran "mur" depuis l'appli employé (scan du QR affiché
 * à l'écran) — voir App\Support\KioskLock. Même convention que le reste de
 * l'API v1.0 (règle 8, CLAUDE.md) : id_user n'est pas vérifié comme étant
 * réellement un compte employee_afc/admin, seulement mémorisé pour l'audit.
 */
```

par :

```php
/**
 * Déverrouillage d'un écran "mur" depuis l'appli employé (scan du QR affiché
 * à l'écran) — voir App\Support\KioskLock. Deux jetons distincts et
 * obligatoires : `token` (jeton de déverrouillage kiosk, prouve qu'un QR
 * affiché à l'écran a été scanné) et `session_token` (jeton Sanctum,
 * prouve que le scanneur est un compte employee_afc/admin authentifié —
 * voir App\Support\ApiAuthentification, qui utilise délibérément un nom de
 * champ différent de `token` pour éviter toute collision entre les deux).
 */
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthKioskLockTest`
Expected: PASS (3 tests) — `kiosk_unlock_tokens` est une table à jour sur cette base locale (créée par une migration déjà jouée aux plans précédents), aucun skip attendu.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/KioskLockController.php tests/Feature/AuthKioskLockTest.php
git commit -m "feat: deverrouillerEcranKiosk exige un jeton de session distinct, réservé au staff"
```

---

## Task 7: Création boutique/produit — `ProductsController::storeProduct` + `ShopsController::addShop`

**Files:**
- Modify: `app/Http/Controllers/API/ProductsController.php:80-92` (bloc de validation de `storeProduct`)
- Modify: `app/Http/Controllers/API/ShopsController.php:48-54` (bloc de validation de `addShop`)
- Test: `tests/Feature/AuthCreationBoutiqueProduitTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()`.

Ces deux endpoints n'ont aucun appelant connu dans les 4 apps (confirmé par grep lors de l'audit du 2026-09-01, voir `ARCHITECTURE.md` §12) — probablement du code mort côté client, mais restaient exploitables directement contre l'API. Réservés au staff (`admin`/`employee_afc`), cohérent avec le fait qu'aucune app cliente ne les appelle et qu'il s'agit d'une action de création de catalogue, pas d'une action en libre-service.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthCreationBoutiqueProduitTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthCreationBoutiqueProduitTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    public function test_storeProduct_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/storeProduct', ['designation_tech' => 'Test'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_storeProduct_avec_un_compte_agent_c_est_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/storeProduct', ['token' => $jeton, 'designation_tech' => 'Test'])
            ->assertOk()->assertJsonPath('response', 403);
    }

    public function test_addShop_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/addShop', ['shop_name' => 'Test'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_addShop_avec_un_compte_agent_c_est_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/addShop', ['token' => $jeton, 'shop_name' => 'Test'])
            ->assertOk()->assertJsonPath('response', 403);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthCreationBoutiqueProduitTest`
Expected: FAIL sur les 4 tests — aucune authentification aujourd'hui, la requête tombe directement dans la validation des champs métier (`422` ou une autre réponse que `401`/`403`).

- [ ] **Step 3: Modifier `storeProduct`**

Dans `app/Http/Controllers/API/ProductsController.php`, remplacer :

```php
    public function storeProduct(Request $request)
    {

        $request->validate([
```

par :

```php
    public function storeProduct(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        if (! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Seuls un employé ou un administrateur peuvent créer un produit."]);
        }

        $request->validate([
```

- [ ] **Step 4: Modifier `addShop`**

Dans `app/Http/Controllers/API/ShopsController.php`, remplacer :

```php
    public function addShop(Request $request)
    {
        $request->validate([
```

par :

```php
    public function addShop(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        if (! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Seuls un employé ou un administrateur peuvent créer une boutique."]);
        }

        $request->validate([
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthCreationBoutiqueProduitTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ProductsController.php app/Http/Controllers/API/ShopsController.php tests/Feature/AuthCreationBoutiqueProduitTest.php
git commit -m "feat: storeProduct/addShop exigent un jeton, réservés au staff"
```

---

## Task 8: Annulation côté client ou décline avant prise — `ClandoController::declinCommand`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:602-635`
- Test: `tests/Feature/AuthDeclinCommandTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()`.

Endpoint à double usage, confirmé par grep sur les 3 apps avant l'écriture de ce plan : appelé par `plouletafcapp` (le client annule sa propre course, `Clando.id_user`) **et** par `pouletafc_agent` (un agent décline une course qui vient de sonner, avant de l'avoir prise — `Clando.id_agent` encore `null` à ce stade). La règle d'autorisation reflète les deux usages légitimes : autorisé si l'appelant est le client propriétaire (`id_user`), OU si la course n'est pas encore prise (`id_agent` encore `null` — n'importe quel agent authentifié peut décliner une offre non assignée), OU s'il est `admin`/`employee_afc`. Refusé (403) uniquement quand la course est déjà assignée à un agent précis et que l'appelant n'est ni ce client, ni cet agent, ni du staff.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthDeclinCommandTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinCommandTest extends TestCase
{
    private array $utilisateursCrees = [];
    private ?Clando $clando = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('clando')) {
            $this->markTestSkipped('Table clando absente de cette base locale.');
        }
    }

    protected function tearDown(): void
    {
        $this->clando?->delete();
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerUtilisateur(string $role): User
    {
        $utilisateur = User::factory()->create(['role' => $role, 'status' => 'Success']);
        $this->utilisateursCrees[] = $utilisateur;

        return $utilisateur;
    }

    public function test_declinCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/declinCommand?id_clando=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinCommand_le_client_proprietaire_peut_annuler(): void
    {
        $client = $this->creerUtilisateur('user');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'status' => 'want',
            'price' => 1000,
        ]);

        $jeton = $client->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 200);
    }

    public function test_declinCommand_un_agent_peut_decliner_une_course_non_assignee(): void
    {
        $client = $this->creerUtilisateur('user');
        $agent = $this->creerUtilisateur('agent');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'id_agent' => null,
            'status' => 'want',
            'price' => 1000,
        ]);

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 200);
    }

    public function test_declinCommand_un_tiers_sans_lien_c_est_403(): void
    {
        $client = $this->creerUtilisateur('user');
        $agentAssigne = $this->creerUtilisateur('agent');
        $intrus = $this->creerUtilisateur('agent');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $intrus->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 403);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthDeclinCommandTest`
Expected: skip global si `clando` est absente localement (attendu) ; sinon FAIL sur les 4 tests (aucune authentification ni vérification aujourd'hui).

- [ ] **Step 3: Modifier `declinCommand`**

Dans `app/Http/Controllers/API/ClandoController.php`, remplacer :

```php
     public function declinCommand(Request $request)
    {

         $order = DB::table('declin_command')->insert([
             'id_user' => $request->id_user,
             'id_clando'=>$request->id_clando


             ]);



         if($order)
         {
```

par :

```php
     public function declinCommand(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $clando = Clando::find($request->id_clando);

        if (! $clando) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable']);
        }

        $estLeClient = (int) $clando->id_user === $utilisateur->id;
        $nonEncoreAssignee = $clando->id_agent === null && $utilisateur->role === 'agent';
        $estStaff = app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur);

        if (! $estLeClient && ! $nonEncoreAssignee && ! $estStaff) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas concerné par cette course."]);
        }

         $order = DB::table('declin_command')->insert([
             'id_user' => $utilisateur->id,
             'id_clando'=>$request->id_clando


             ]);



         if($order)
         {
```

Note : `$clando->id_user` est comparé avec un cast `(int)` par précaution (même leçon que `id_agent` au Plan 2) — vérifier le vrai type de `clando.id_user` via `Schema::hasColumn`/`SHOW COLUMNS` avant de committer si la table `clando` devient inspectable sur l'environnement d'exécution ; si elle s'avère être un entier natif, le cast reste sans effet néfaste (`(int) 42 === 42`).

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthDeclinCommandTest`
Expected: PASS (4 tests) si `clando` existe dans l'environnement d'exécution, sinon skip global (attendu sur cette base locale, voir Global Constraints).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php tests/Feature/AuthDeclinCommandTest.php
git commit -m "feat: declinCommand exige un jeton, autorise client propriétaire/agent non-assigné/staff"
```

---

## Task 9: Décline d'une offre de commande — `OrderController::declinOrderCommand`

**Files:**
- Modify: `app/Http/Controllers/API/OrderController.php:451-464`
- Test: `tests/Feature/AuthDeclinOrderCommandTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`.

Contrairement à `declinCommand` (Tâche 8), cet endpoint est appelé uniquement côté agent/employé (`pouletafc_agent`, `empolyeeafc` — confirmé par grep, jamais `plouletafcapp`) et n'écrit qu'une ligne d'audit (`declin_command`) sans toucher au statut de la commande elle-même — pas de ressource métier à protéger par une vérification de propriété, seulement l'identité de qui a décliné. Le correctif se limite à exiger un jeton et à ne plus faire confiance à l'`id_user` envoyé par le client pour cette ligne d'audit.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthDeclinOrderCommandTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinOrderCommandTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    public function test_declinOrderCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/declinOrderCommand?id_order=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinOrderCommand_enregistre_l_appelant_authentifie_pas_le_id_user_du_client(): void
    {
        if (! Schema::hasTable('declin_command')) {
            $this->markTestSkipped('Table declin_command absente de cette base locale.');
        }

        $victime = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $victime;
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinOrderCommand?token=' . $jeton . '&id_order=42&id_user=' . $victime->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('declin_command', ['id_user' => $agent->id, 'id_order' => 42]);
        $this->assertDatabaseMissing('declin_command', ['id_user' => $victime->id, 'id_order' => 42]);

        \Illuminate\Support\Facades\DB::table('declin_command')->where('id_user', $agent->id)->where('id_order', 42)->delete();
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthDeclinOrderCommandTest`
Expected: FAIL sur `test_declinOrderCommand_sans_jeton_c_est_401` (aucune authentification aujourd'hui) ; le second skip si `declin_command` est absente localement, sinon échoue (`id_user` du client toujours utilisé aujourd'hui).

- [ ] **Step 3: Modifier `declinOrderCommand`**

Dans `app/Http/Controllers/API/OrderController.php`, remplacer :

```php
      public function declinOrderCommand(Request $request)
        {
        
         $order = DB::table('declin_command')->insert([
             'id_user' => $request->id_user, 
             'id_order'=>$request->id_order
             ]);
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
            
        }
```

par :

```php
      public function declinOrderCommand(Request $request)
        {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $order = DB::table('declin_command')->insert([
             'id_user' => $utilisateur->id, 
             'id_order'=>$request->id_order
             ]);
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
            
        }
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthDeclinOrderCommandTest`
Expected: PASS (2 tests) ou skip du second selon `declin_command`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/OrderController.php tests/Feature/AuthDeclinOrderCommandTest.php
git commit -m "feat: declinOrderCommand exige un jeton, journalise l'appelant authentifié"
```

---

## Self-Review Notes (pour l'exécutant)

- Ce plan referme le dernier gap connu documenté à `ARCHITECTURE.md` §14 (`declinCommand`/`declinOrderCommand`) en plus de son périmètre initialement annoncé, et ajoute `updateDeliveryPosition` (même classe de faille qu'`updateAgentPosition`, gap découvert en préparant ce plan) — les deux ajouts ont été explicitement validés par l'utilisateur avant l'écriture de ce plan.
- Une fois ce plan exécuté et revu, **c'est la fin des 3 plans d'authentification** : mettre à jour `CLAUDE.md` (règle 8, qui affirme aujourd'hui qu'aucune route `v1.0` n'est authentifiée) et `ARCHITECTURE.md` pour refléter l'état réel — pas avant.
- `carts`/`cart_items`/`declin_command`/`clando.id_user` n'ont pas été vérifiés colonne-par-colonne (type réel) avant l'écriture de ce plan, contrairement à `agents`/`order_details.id_agent` déjà cartographiés aux plans précédents — chaque tâche concernée porte sa propre note à ce sujet, à vérifier à l'exécution plutôt qu'à supposer.
