# Authentification API v1.0 — Courses et commandes (Plan 2/3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Protéger les endpoints de prise/fin/déclin de course et de commande (`ClandoController`, `OrderController`, `GeolocalisationController::updateAgentPosition`) contre l'IDOR : exiger un jeton valide, et pour les actions sur une ressource déjà assignée, vérifier que l'appelant authentifié est bien l'agent assigné (ou `admin`/`employee_afc`, qui gardent leur vue globale).

**Architecture:** Chaque méthode protégée commence par résoudre l'utilisateur via `App\Support\ApiAuthentification::utilisateurOuErreur()` (construite au Plan 1, déjà en production) — 401 immédiat si absent/invalide. Pour les actions sur une ressource déjà assignée à un agent, une nouvelle méthode `ApiAuthentification::estStaff()` autorise `admin`/`employee_afc` à contourner la vérification de propriété — cohérent avec les règles 15/16 de CLAUDE.md (l'employé garde sa vue globale de dispatcher). Partout où le code faisait jusqu'ici confiance à `$request->id_user`/`$request->id_agent` pour savoir qui appelle, cette valeur est remplacée par `$utilisateur->id` (dérivé du jeton) — jamais supprimée du payload (rétrocompatible, l'app peut continuer à l'envoyer, elle est simplement ignorée).

**Tech Stack:** Laravel, Sanctum (`App\Support\ApiAuthentification`, Plan 1), PHPUnit (`tests/Feature`, pas de `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-09-01-shared-api-authentication-design.md` (§6.4, §6.5)

## Global Constraints

- Le jeton est transmis en paramètre de requête (`token`), jamais en en-tête `Authorization` — utiliser `app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request)`, jamais un appel direct à Sanctum.
- Enveloppe JSON partout : `{"response": <code>, "message": "..."}`. `401` = pas de jeton valide. `403` = jeton valide mais pas propriétaire de la ressource ciblée.
- `admin` et `employee_afc` contournent toute vérification de propriété (règles 15/16 CLAUDE.md) — jamais les autres rôles.
- Ce dépôt n'utilise pas `RefreshDatabase`/`DatabaseTransactions` : les tests tournent sur la vraie base configurée, nettoient eux-mêmes ce qu'ils créent en `tearDown()`.
- **État réel du schéma local, vérifié le 2026-09-01 avant d'écrire ce plan — lire avant de s'inquiéter d'un skip inattendu :**
  - La table `clando` **n'existe pas du tout** sur cette base locale (aucune migration de ce dépôt ne la crée — créée à la main sur le serveur, jamais répliquée ici). **Tout test touchant `Clando` doit commencer par `if (! Schema::hasTable('clando')) { $this->markTestSkipped('Table clando absente de cette base locale.'); }`** — ce n'est pas un bug de ce plan, ne pas essayer de la recréer par migration sans schéma de référence exact.
  - La table `order_details` a été patchée le 2026-09-01 (migration `2026_07_21_000005_patch_order_details_table`, longtemps restée en attente, jouée juste avant ce plan) et possède maintenant `ref`, `id_agent`, `id_user`, `status`, `price`, `commission_agent`, `payment_method`, `delivery_code`, `delivery_type`, `latAgent`, `lonAgent`, `delivery_fees`, `cancel_reason`, `cancelled_at`, `cancelled_by`, `status_paiement`, `matricule_vehicule` — la plupart des tests `OrderController` peuvent donc tourner pour de vrai. **Elle n'a pas encore `agent_arrived_at`** (ajoutée par `2026_08_26_000007_arrivee_agent_order`, encore en attente) — guarder spécifiquement ce test-là.
  - La table `agents` sur cette base locale **n'a pas la colonne `id_user`** (ni `freeStatus`, ni `deposit_recu`, ni `balance`, ni `status`, ni `matricule_vehicule`, ni `type`) — seulement `id`, `registration_number`, `agent_name`, `phone`, `national_identity_card_number`, `location_plan_file`, `identity_card_file`, `photo`, `caution_id`, timestamps. Aucune migration de ce dépôt n'ajoute `id_user` à `agents`. **Tout test qui a besoin de créer un `Agent` lié à un `User` via `id_user` doit commencer par `if (! Schema::hasColumn('agents', 'id_user')) { $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.'); }`** — cela couvre la quasi-totalité des tests de succès de ce plan (la logique métier de `takeClandoCommand`/`takeOrderCommand`/`terminatedCourse(Order)`/`declinCommandAfterTake(Order)` interroge `Agent::where('id_user', ...)`).
  - Les tables `mouvements_financiers` et `credit_agents` n'existent pas non plus localement — tout test qui pousse `terminatedCourse`/`terminatedCourseOrder` jusqu'au crédit financier réel a aussi besoin de `Schema::hasTable('mouvements_financiers')` et `Schema::hasTable('credit_agents')`.
  - **Conséquence assumée pour ce plan** : les tests `401`/`403` (le cœur de ce que ce plan ajoute) sont conçus pour s'exécuter **avant** d'atteindre ce code métier fragile — ils passeront réellement sur cette base. Les tests de succès complet (l'agent assigné termine effectivement sa course avec crédit financier) sont, pour la plupart, gardés et skippés ici — le code est écrit et relu avec le même soin, mais leur vérification réelle attendra un environnement dont le schéma est à jour. **Ne jamais essayer de contourner un skip par une migration ou un DDL improvisés** (voir l'incident de la Tâche 3 du Plan 1, `docs/superpowers/plans/2026-09-01-shared-api-authentication-foundation.md`) — si un garde-fou déclenche un skip inattendu, c'est le schéma local qui est en cause, pas le code de ce plan.
- `App\Support\Idempotence::executer($cle, ...)` (déjà en place, utilisé par `takeClandoCommand`/`takeOrderCommand`) court-circuite directement vers l'opération sans toucher `idempotency_keys` quand `$cle` est vide — un test qui n'envoie pas `idempotency_key` n'a donc besoin d'aucune garde sur cette table.

---

## Task 1: `GeolocalisationController::updateAgentPosition` — authentification, identité dérivée du jeton

**Files:**
- Modify: `app/Http/Controllers/API/GeolocalisationController.php:64-89`
- Test: `tests/Feature/AuthPositionAgentTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateurOuErreur(Request $request): User|JsonResponse` (Plan 1, `app/Support/ApiAuthentification.php`).
- Produces: aucune nouvelle interface — dernier maillon de la catégorie "auto-signalement de position".

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthPositionAgentTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthPositionAgentTest extends TestCase
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

    public function test_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateAgentPosition', [
            'id_user' => 999999,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 401);
    }

    public function test_avec_jeton_met_a_jour_la_position_de_l_appelant(): void
    {
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateAgentPosition', [
            'token' => $jeton,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $agent->actual_lat_position_agent, 0.0001);
        $this->assertEqualsWithDelta(13.4, (float) $agent->actual_lon_position_agent, 0.0001);
    }

    public function test_le_id_user_envoye_par_le_client_est_ignore(): void
    {
        $agent = $this->creerAgent();
        $victime = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateAgentPosition', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $victime->refresh();
        $this->assertNull($victime->actual_lat_position_agent, "La position de la victime ne doit jamais être modifiée par le jeton d'un autre compte.");
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthPositionAgentTest`
Expected: FAIL — sans authentification dans le contrôleur, `test_sans_jeton_c_est_401` reçoit `response: 400` (identifiant manquant) au lieu de `401`, et les deux autres tests échouent (aucun champ `token` n'est actuellement lu par ce contrôleur).

- [ ] **Step 3: Modifier `updateAgentPosition`**

Dans `app/Http/Controllers/API/GeolocalisationController.php`, remplacer :

```php
     public function updateAgentPosition(Request $request)
     {
         $idUser = $request->input('id_user');
         $lat = $request->input('lat', $request->input('latitude'));
         $lon = $request->input('lon', $request->input('longitude'));

         if (! $idUser || ! is_numeric($lat) || ! is_numeric($lon)) {
             return response()->json([
                 'response' => 400,
                 'message' => 'Identifiant ou coordonnées manquants',
             ]);
         }

         // Un zéro tombe au large du golfe de Guinée : c'est « rien de relevé »,
         // pas une position, et l'écrire ferait sauter le marqueur sur la carte.
         if ((float) $lat === 0.0 || (float) $lon === 0.0) {
             return response()->json(['response' => 400, 'message' => 'Coordonnées nulles']);
         }

         $modifiees = User::where('id', $idUser)->update([
             'actual_lat_position_agent' => (float) $lat,
             'actual_lon_position_agent' => (float) $lon,
             'position_updated_at' => now(),
         ]);

         return response()->json(['response' => $modifiees ? 200 : 404]);
```

par :

```php
     public function updateAgentPosition(Request $request)
     {
         $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
         if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
             return $utilisateur;
         }

         $lat = $request->input('lat', $request->input('latitude'));
         $lon = $request->input('lon', $request->input('longitude'));

         if (! is_numeric($lat) || ! is_numeric($lon)) {
             return response()->json([
                 'response' => 400,
                 'message' => 'Identifiant ou coordonnées manquants',
             ]);
         }

         // Un zéro tombe au large du golfe de Guinée : c'est « rien de relevé »,
         // pas une position, et l'écrire ferait sauter le marqueur sur la carte.
         if ((float) $lat === 0.0 || (float) $lon === 0.0) {
             return response()->json(['response' => 400, 'message' => 'Coordonnées nulles']);
         }

         /*
          | La position écrite est toujours celle de l'appelant authentifié —
          | jamais celle d'un id_user fourni par le client (spec 2026-09-01,
          | §4 : le paramètre reste accepté pour compatibilité, mais n'a plus
          | aucun effet sur l'identité).
          */
         $modifiees = User::where('id', $utilisateur->id)->update([
             'actual_lat_position_agent' => (float) $lat,
             'actual_lon_position_agent' => (float) $lon,
             'position_updated_at' => now(),
         ]);

         return response()->json(['response' => $modifiees ? 200 : 404]);
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthPositionAgentTest`
Expected: PASS (3 tests) — cette table (`users`) est entièrement à jour sur cette base locale, aucun skip attendu.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/GeolocalisationController.php tests/Feature/AuthPositionAgentTest.php
git commit -m "feat: updateAgentPosition exige un jeton, ignore id_user du client"
```

---

## Task 2: Position en course — `ClandoController::updatePositionAgent` + `OrderController::updatePositionAgentOrder`

**Files:**
- Modify: `app/Support/ApiAuthentification.php` (ajoute `estStaff()`)
- Modify: `app/Http/Controllers/API/ClandoController.php:232-254`
- Modify: `app/Http/Controllers/API/OrderController.php:717-739`
- Test: `tests/Feature/AuthPositionEnCoursTest.php` (nouveau fichier)

**Interfaces:**
- Produces: `App\Support\ApiAuthentification::estStaff(User $utilisateur): bool` — `true` si `admin`/`employee_afc`, consommé par toutes les tâches suivantes de ce plan.
- Consumes: `ApiAuthentification::utilisateurOuErreur()` (Plan 1).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthPositionEnCoursTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPositionEnCoursTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?Clando $clando = null;
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->clando?->delete();
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    private function creerFicheAgent(User $utilisateur): Agent
    {
        $agent = Agent::create(['id_user' => $utilisateur->id]);
        $this->agentsCrees[] = $agent;

        return $agent;
    }

    public function test_updatePositionAgentOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'ref' => 'REF-INEXISTANTE',
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updatePositionAgentOrder_avec_le_jeton_d_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertNull($this->commande->latAgent, "La position ne doit pas bouger quand l'appelant n'est pas l'agent assigné.");
    }

    public function test_updatePositionAgentOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $this->commande->latAgent, 0.0001);
    }

    public function test_updatePositionAgentOrder_employee_afc_contourne_la_propriete(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $employe = $this->creerUtilisateur('employee_afc');

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $employe->createToken('employee-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthPositionEnCoursTest`
Expected: FAIL — `test_updatePositionAgentOrder_sans_jeton_c_est_401` reçoit `response: 200` (l'endpoint répond même sans jeton aujourd'hui) ; les autres échouent sur `response: 403` attendu vs `200` reçu (aucune vérification de propriété actuellement), sauf si `agents.id_user` est absent localement (skip, voir Global Constraints — ce n'est pas un échec de ce step).

- [ ] **Step 3: Ajouter `estStaff()` à `ApiAuthentification`**

Dans `app/Support/ApiAuthentification.php`, ajouter cette méthode publique à la classe (après `utilisateurOuErreur`) :

```php

    /**
     * `admin` et `employee_afc` gardent une vue globale légitime sur toutes
     * les courses/commandes (règles 15/16, CLAUDE.md) — ils contournent la
     * vérification de propriété sur les ressources déjà assignées à un
     * agent, contrairement à tout autre rôle.
     */
    public function estStaff(User $utilisateur): bool
    {
        return in_array($utilisateur->role, ['admin', 'employee_afc'], true);
    }
```

- [ ] **Step 4: Modifier `ClandoController::updatePositionAgent`**

Dans `app/Http/Controllers/API/ClandoController.php`, remplacer :

```php
        public function updatePositionAgent(Request $request)
    {
        
          $order = Clando::where('ref',$request->ref);
          
          $update = $order
          ->update([
              
              'latAgent'=>$request->latAgent,
              'lonAgent'=>$request->lonAgent,
              ]);
           
          
          if($update)
          {
                if($order) return response()->json(['response' => 200, 'data'=>  $order->get()  ]);
          }
            

        else return response()->json(['response' => 404]);
        
        
    }
```

par :

```php
        public function updatePositionAgent(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $clando = Clando::where('ref', $request->ref)->first();

        if (! $clando) {
            return response()->json(['response' => 404]);
        }

        if ($clando->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette course."]);
        }

        $update = $clando->update([
            'latAgent' => $request->latAgent,
            'lonAgent' => $request->lonAgent,
        ]);

        if ($update) {
            return response()->json(['response' => 200, 'data' => $clando]);
        }

        return response()->json(['response' => 404]);
    }
```

- [ ] **Step 5: Modifier `OrderController::updatePositionAgentOrder`**

Dans `app/Http/Controllers/API/OrderController.php`, remplacer :

```php
         public function updatePositionAgentOrder(Request $request)
    {
        
          $order = order_detail::where('ref',$request->ref);
          
          $update = $order
          ->update([
              
              'latAgent'=>$request->latAgent,
              'lonAgent'=>$request->lonAgent,
              ]);
           
          
          if($update)
          {
                if($order) return response()->json(['response' => 200, 'data'=>  $order->get()  ]);
          }
            

        else return response()->json(['response' => 404]);
        
        
    }
```

par :

```php
         public function updatePositionAgentOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $order = order_detail::where('ref', $request->ref)->first();

        if (! $order) {
            return response()->json(['response' => 404]);
        }

        if ($order->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
        }

        $update = $order->update([
            'latAgent' => $request->latAgent,
            'lonAgent' => $request->lonAgent,
        ]);

        if ($update) {
            return response()->json(['response' => 200, 'data' => $order]);
        }

        return response()->json(['response' => 404]);
    }
```

Note : `$clando->id_agent`/`$order->id_agent` viennent de la base (donc déjà castés en entier par Eloquent si la colonne est numérique) et `$utilisateur->id` est un entier — la comparaison stricte `!==` est donc fiable ; si un jour `id_agent` est nullable et vaut `null` pour une ressource jamais prise, `null !== (int)` est toujours vrai, ce qui refuse correctement l'accès (comportement voulu : personne ne doit pouvoir "mettre à jour la position" d'une course non encore prise par cette route).

- [ ] **Step 6: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthPositionEnCoursTest`
Expected: PASS (4 tests) — `order_details` a `ref`/`id_agent`/`latAgent`/`lonAgent` sur cette base (voir Global Constraints), donc ce test ne devrait **pas** skipper, sauf si `agents.id_user` manque encore (auquel cas skip attendu, pas un échec).

- [ ] **Step 7: Commit**

```bash
git add app/Support/ApiAuthentification.php app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthPositionEnCoursTest.php
git commit -m "feat: updatePositionAgent(Order) exige un jeton et vérifie la propriété de la course"
```

---

## Task 3: Prise de course/commande — `ClandoController::takeClandoCommand` + `OrderController::takeOrderCommand`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:299-414`
- Modify: `app/Http/Controllers/API/OrderController.php:466-558`
- Test: `tests/Feature/AuthPriseCommandeTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()` (Plan 1).

Ces deux méthodes n'ont pas de "propriétaire existant" à vérifier (elles n'assignent une course/commande que si `id_agent` est encore `null`) — la correction consiste uniquement à assigner l'identité de l'appelant authentifié, jamais celle envoyée par le client.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthPriseCommandeTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPriseCommandeTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_takeOrderCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/takeOrderCommand?ref=REF-INEXISTANTE&id_agent=1')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_takeOrderCommand_assigne_l_appelant_authentifie_pas_le_id_agent_du_client(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/freeStatus absentes de cette base locale.');
        }

        $agentAppelant = $this->creerAgent();
        Agent::create(['id_user' => $agentAppelant->id]);
        $victime = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-TAKE-' . uniqid(),
            'id_agent' => null,
            'status' => 'want',
            'price' => 500,
            'commission_agent' => 0,
        ]);

        $jeton = $agentAppelant->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/takeOrderCommand?token=' . $jeton . '&ref=' . $this->commande->ref . '&id_agent=' . $victime->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame($agentAppelant->id, $this->commande->id_agent, "La commande doit être assignée à l'appelant authentifié, jamais au id_agent fourni par le client.");
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthPriseCommandeTest`
Expected: FAIL — `test_takeOrderCommand_sans_jeton_c_est_401` reçoit une autre réponse que `401` (l'endpoint ne vérifie aucun jeton aujourd'hui) ; le second test échoue sur `assertSame` (la commande serait assignée à `$victime->id`, pas à l'appelant), sauf skip si les colonnes `agents` manquent.

- [ ] **Step 3: Modifier `ClandoController::takeClandoCommand`**

Dans `app/Http/Controllers/API/ClandoController.php`, la méthode commence ainsi :

```php
     public function takeClandoCommand(Request $request)
    {
        return Idempotence::executer($request->input('idempotency_key'), 'takeClandoCommand', function () use ($request) {

          $order = Clando::where('ref',$request->ref)->first();
          
          
          

         
         
         
          $solde =(new Fonction())->solde($request->id_agent);
```

Remplacer par :

```php
     public function takeClandoCommand(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        return Idempotence::executer($request->input('idempotency_key'), 'takeClandoCommand', function () use ($request, $utilisateur) {

          $order = Clando::where('ref',$request->ref)->first();

          $solde =(new Fonction())->solde($utilisateur->id);
```

Puis, plus bas dans la même méthode, chaque usage de `$request->id_agent` qui désigne l'agent en train de prendre la course devient `$utilisateur->id` :

```php
          $agent = Agent::where('id_user',$request->id_agent)->first();
        
        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]); 
        }
        
        
        
        
        $freeStatusAgent = Agent::where('id_user',$request->id_agent)->update([
            
            'freeStatus' => 0
            
            ]);
```

devient :

```php
          $agent = Agent::where('id_user',$utilisateur->id)->first();
        
        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]); 
        }
        
        
        
        
        $freeStatusAgent = Agent::where('id_user',$utilisateur->id)->update([
            
            'freeStatus' => 0
            
            ]);
```

et enfin, plus bas, l'assignation elle-même :

```php
          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $request->id_agent,
                  'status'=>  'process',
                  'latAgent'=> $request->latAgent,
                  'lonAgent'=> $request->lonAgent,
                  'matricule_vehicule'=> $agent->matricule_vehicule
                  
                  
                  ]);
```

devient :

```php
          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $utilisateur->id,
                  'status'=>  'process',
                  'latAgent'=> $request->latAgent,
                  'lonAgent'=> $request->lonAgent,
                  'matricule_vehicule'=> $agent->matricule_vehicule
                  
                  
                  ]);
```

(Tous les autres usages de `$request->id_agent` dans cette méthode — il n'y en a pas d'autres après ces trois blocs — n'existent pas ; ne pas chercher plus loin.)

- [ ] **Step 4: Modifier `OrderController::takeOrderCommand`**

Même transformation, sur `app/Http/Controllers/API/OrderController.php`. Le début de la méthode :

```php
     public function takeOrderCommand(Request $request)
    {
        return Idempotence::executer($request->input('idempotency_key'), 'takeOrderCommand', function () use ($request) {

          $order = order_detail::where('ref',$request->ref)->first();
          
        
       
       
          
          $solde =(new Fonction())->solde($request->id_agent);
```

devient :

```php
     public function takeOrderCommand(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        return Idempotence::executer($request->input('idempotency_key'), 'takeOrderCommand', function () use ($request, $utilisateur) {

          $order = order_detail::where('ref',$request->ref)->first();

          $solde =(new Fonction())->solde($utilisateur->id);
```

Puis :

```php
          $agent = Agent::where('id_user',$request->id_agent)->first();
        
        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]); 
        }
        
          $freeStatusAgent = Agent::where('id_user',$request->id_agent)->update([
            
            'freeStatus' => 0
            
            ]);
```

devient :

```php
          $agent = Agent::where('id_user',$utilisateur->id)->first();
        
        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]); 
        }
        
          $freeStatusAgent = Agent::where('id_user',$utilisateur->id)->update([
            
            'freeStatus' => 0
            
            ]);
```

Et enfin :

```php
          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $request->id_agent,
                  'status'=>  'process',
                  'latAgent'=> $request->latAgent,
                  'lonAgent'=> $request->lonAgent,
                  'matricule_vehicule'=> $agent->matricule_vehicule
                  
                  
                  ]);
```

devient :

```php
          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $utilisateur->id,
                  'status'=>  'process',
                  'latAgent'=> $request->latAgent,
                  'lonAgent'=> $request->lonAgent,
                  'matricule_vehicule'=> $agent->matricule_vehicule
                  
                  
                  ]);
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthPriseCommandeTest`
Expected: PASS (2 tests), sauf skip si `agents.id_user`/`agents.freeStatus` manquent sur cette base (attendu, voir Global Constraints).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthPriseCommandeTest.php
git commit -m "feat: takeClandoCommand/takeOrderCommand exigent un jeton, assignent l'appelant authentifié"
```

---

## Task 4: Statut "en route" — `ClandoController::mapAftertake` + `OrderController::mapAftertakeOrder`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:680-694`
- Modify: `app/Http/Controllers/API/OrderController.php:742-755`
- Test: `tests/Feature/AuthMapAftertakeTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()` (Task 2).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthMapAftertakeTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthMapAftertakeTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    private function creerFicheAgent(User $utilisateur): Agent
    {
        $agent = Agent::create(['id_user' => $utilisateur->id]);
        $this->agentsCrees[] = $agent;

        return $agent;
    }

    public function test_mapAftertakeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/mapAftertakeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_mapAftertakeOrder_avec_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status);
    }

    public function test_mapAftertakeOrder_avec_le_bon_agent_passe_a_take(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('take', $this->commande->status);
    }

    public function test_mapAftertakeOrder_admin_contourne_la_propriete(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $admin = $this->creerUtilisateur('admin');

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $admin->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthMapAftertakeTest`
Expected: FAIL sur les 4 tests (aucune authentification ni vérification de propriété actuellement), sauf skip si `agents.id_user` manque.

- [ ] **Step 3: Modifier `ClandoController::mapAftertake`**

Dans `app/Http/Controllers/API/ClandoController.php`, remplacer :

```php
    public function mapAftertake(Request $request)
    {
         $order = Clando::where('ref',$request->ref)->update([
                
                  'status'=>  'take'
                  
                  
                  ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
    }
```

Remplacer par :

```php
    public function mapAftertake(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $clando = Clando::where('ref', $request->ref)->first();

        if (! $clando) {
            return response()->json(['response' => 400]);
        }

        if ($clando->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette course."]);
        }

        $order = $clando->update(['status' => 'take']);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }
```

- [ ] **Step 4: Modifier `OrderController::mapAftertakeOrder`**

Dans `app/Http/Controllers/API/OrderController.php`, remplacer :

```php
     public function mapAftertakeOrder(Request $request)
    {
         $order = order_detail::where('ref',$request->ref)->update([
                
                  'status'=>  'take'
                  
                  
                  ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
```

par :

```php
     public function mapAftertakeOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $commande = order_detail::where('ref', $request->ref)->first();

        if (! $commande) {
            return response()->json(['response' => 400]);
        }

        if ($commande->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
        }

         $order = $commande->update([
                
                  'status'=>  'take'
                  
                  
                  ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
```

(le reste de la méthode, après ce bloc, est inchangé.)

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthMapAftertakeTest`
Expected: PASS (4 tests) — `order_details` a `ref`/`id_agent`/`status`, ce test ne devrait pas skipper sauf si `agents.id_user` manque encore.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthMapAftertakeTest.php
git commit -m "feat: mapAftertake(Order) exige un jeton et vérifie la propriété de la course"
```

---

## Task 5: Déclin après prise — `ClandoController::declinCommandAfterTake` + `OrderController::declinCommandAfterTakeOrder`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:634-678`
- Modify: `app/Http/Controllers/API/OrderController.php:918-962`
- Test: `tests/Feature/AuthDeclinApresPriseTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()` (Task 2).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthDeclinApresPriseTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinApresPriseTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    private function creerFicheAgent(User $utilisateur): Agent
    {
        $agent = Agent::create(['id_user' => $utilisateur->id]);
        $this->agentsCrees[] = $agent;

        return $agent;
    }

    public function test_declinCommandAfterTakeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinCommandAfterTakeOrder_avec_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status, "Le statut ne doit pas changer si l'appelant n'est pas l'agent assigné.");
    }

    public function test_declinCommandAfterTakeOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/freeStatus absentes de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'reason' => 'Client injoignable'])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('declin', $this->commande->status);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthDeclinApresPriseTest`
Expected: FAIL sur les 3 tests (aucune authentification ni vérification de propriété actuellement), sauf skips selon les colonnes disponibles.

- [ ] **Step 3: Modifier `ClandoController::declinCommandAfterTake`**

Remplacer :

```php
      public function declinCommandAfterTake(Request $request)
        {
        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = Clando::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable.']);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        $champs = ['status' => 'declin'];

        foreach ([
            'cancel_reason' => \App\Support\AnnulationDeCommande::motifValide($motif)
                ? \App\Support\AnnulationDeCommande::nettoyerLeMotif($motif)
                : null,
            'cancelled_at' => now(),
            'cancelled_by' => 'agent',
        ] as $colonne => $valeur) {
            if ($valeur !== null && \App\Support\ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $champs[$colonne] = $valeur;
            }
        }

        $order = $ligne->update($champs);

        $freeStatusAgent = Agent::where('id_user', $request->id_user)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }
```

par :

```php
      public function declinCommandAfterTake(Request $request)
        {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = Clando::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable.']);
        }

        if ($ligne->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette course."]);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        $champs = ['status' => 'declin'];

        foreach ([
            'cancel_reason' => \App\Support\AnnulationDeCommande::motifValide($motif)
                ? \App\Support\AnnulationDeCommande::nettoyerLeMotif($motif)
                : null,
            'cancelled_at' => now(),
            'cancelled_by' => 'agent',
        ] as $colonne => $valeur) {
            if ($valeur !== null && \App\Support\ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $champs[$colonne] = $valeur;
            }
        }

        $order = $ligne->update($champs);

        $freeStatusAgent = Agent::where('id_user', $utilisateur->id)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }
```

- [ ] **Step 4: Modifier `OrderController::declinCommandAfterTakeOrder`**

Même transformation. Remplacer :

```php
          public function declinCommandAfterTakeOrder(Request $request)
        {
        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = order_detail::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable.']);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        $champs = ['status' => 'declin'];

        foreach ([
            'cancel_reason' => \App\Support\AnnulationDeCommande::motifValide($motif)
                ? \App\Support\AnnulationDeCommande::nettoyerLeMotif($motif)
                : null,
            'cancelled_at' => now(),
            'cancelled_by' => 'agent',
        ] as $colonne => $valeur) {
            if ($valeur !== null && \App\Support\ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $champs[$colonne] = $valeur;
            }
        }

        $order = $ligne->update($champs);

        $freeStatusAgent = Agent::where('id_user', $request->id_user)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }
```

par :

```php
          public function declinCommandAfterTakeOrder(Request $request)
        {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = order_detail::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable.']);
        }

        if ($ligne->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        $champs = ['status' => 'declin'];

        foreach ([
            'cancel_reason' => \App\Support\AnnulationDeCommande::motifValide($motif)
                ? \App\Support\AnnulationDeCommande::nettoyerLeMotif($motif)
                : null,
            'cancelled_at' => now(),
            'cancelled_by' => 'agent',
        ] as $colonne => $valeur) {
            if ($valeur !== null && \App\Support\ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $champs[$colonne] = $valeur;
            }
        }

        $order = $ligne->update($champs);

        $freeStatusAgent = Agent::where('id_user', $utilisateur->id)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthDeclinApresPriseTest`
Expected: PASS (3 tests), sauf skips selon les colonnes `agents` disponibles localement.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthDeclinApresPriseTest.php
git commit -m "feat: declinCommandAfterTake(Order) exige un jeton et vérifie la propriété de la course"
```

---

## Task 6: Arrivée chez le client — `ClandoController::arriveeAgent` + `OrderController::arriveeAgentOrder`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:708-723`
- Modify: `app/Http/Controllers/API/OrderController.php:976-991`
- Test: `tests/Feature/AuthArriveeAgentTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()` (Plan 1). **Pas** de `estStaff()` ici : ces deux méthodes filtrent déjà `->where('id_agent', ...)` au niveau de la requête — remplacer la valeur source (`$request->id_user` → `$utilisateur->id`) suffit, sans bypass staff (elles ne sont pas listées comme réutilisées par `employee_afc` dans CLAUDE.md règles 15/16 — cohérent avec ce filtre déjà strict aujourd'hui).

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthArriveeAgentTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthArriveeAgentTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_arriveeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/arriveeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_arriveeOrder_avec_un_autre_agent_ne_trouve_pas_la_commande(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-ARR-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        // La requête ne trouve pas la commande (filtrée par id_agent =
        // l'appelant) : réponse identique à "commande introuvable", pas un
        // 403 distinct — comportement déjà en place avant ce plan, conservé.
        $this->postJson('/api/v1.0/arriveeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 400);

        $this->commande->refresh();
        $this->assertNull($this->commande->agent_arrived_at);
    }

    public function test_arriveeOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('order_details', 'agent_arrived_at')) {
            $this->markTestSkipped('Colonne agents.id_user ou order_details.agent_arrived_at absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);

        $this->commande = order_detail::create([
            'ref' => 'TEST-ARR-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/arriveeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertNotNull($this->commande->agent_arrived_at);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthArriveeAgentTest`
Expected: FAIL sur `test_arriveeOrder_sans_jeton_c_est_401` (aucune authentification actuellement) ; les deux autres dépendent de colonnes potentiellement absentes (skip attendu) — s'ils tournent, ils devraient déjà passer aujourd'hui puisque le filtre `id_agent` existe déjà (c'est pour ça que ce test n'est pas un TDD classique sur ces deux cas, seul le premier prouve un manque réel).

- [ ] **Step 3: Modifier `ClandoController::arriveeAgent`**

Remplacer :

```php
    public function arriveeAgent(Request $request)
    {
        $clando = Clando::where('ref', $request->ref)
            ->where('id_agent', $request->id_user)
            ->first();

        if (! $clando) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable']);
        }

        if (! $clando->agent_arrived_at) {
            $clando->update(['agent_arrived_at' => now()]);
        }

        return response()->json(['response' => 200]);
    }
```

par :

```php
    public function arriveeAgent(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $clando = Clando::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $clando) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable']);
        }

        if (! $clando->agent_arrived_at) {
            $clando->update(['agent_arrived_at' => now()]);
        }

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 4: Modifier `OrderController::arriveeAgentOrder`**

Remplacer :

```php
    public function arriveeAgentOrder(Request $request)
    {
        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $request->id_user)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        if (! $order->agent_arrived_at) {
            $order->update(['agent_arrived_at' => now()]);
        }

        return response()->json(['response' => 200]);
    }
```

par :

```php
    public function arriveeAgentOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        if (! $order->agent_arrived_at) {
            $order->update(['agent_arrived_at' => now()]);
        }

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthArriveeAgentTest`
Expected: PASS (3 tests) ou skip sur les deux derniers selon `order_details.agent_arrived_at` (voir Global Constraints — cette colonne est encore en attente localement).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthArriveeAgentTest.php
git commit -m "feat: arriveeAgent(Order) exige un jeton, dérive l'identité du jeton"
```

---

## Task 7: Mode de paiement — `ClandoController::setPaymentMethodClando` + `OrderController::setPaymentMethodOrder`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:732-749`
- Modify: `app/Http/Controllers/API/OrderController.php:1001-1018`
- Test: `tests/Feature/AuthPaiementModeTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()` (Plan 1). Même raisonnement que la Task 6 : filtre déjà en place, pas de bypass staff.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthPaiementModeTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPaiementModeTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_setPaymentMethodOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['ref' => 'REF-INEXISTANTE', 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_setPaymentMethodOrder_avec_un_autre_agent_ne_trouve_pas_la_commande(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-PAY-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 400);

        $this->commande->refresh();
        $this->assertNull($this->commande->payment_method);
    }

    public function test_setPaymentMethodOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);

        $this->commande = order_detail::create([
            'ref' => 'TEST-PAY-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('OM', $this->commande->payment_method);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthPaiementModeTest`
Expected: FAIL sur `test_setPaymentMethodOrder_sans_jeton_c_est_401` ; les deux autres skips ou déjà-passants selon la colonne `agents.id_user`.

- [ ] **Step 3: Modifier `ClandoController::setPaymentMethodClando`**

Remplacer :

```php
    public function setPaymentMethodClando(Request $request)
    {
        if (! in_array($request->payment_method, ['LIVRAISON', 'OM'], true)) {
            return response()->json(['response' => 400, 'message' => 'Mode de paiement invalide']);
        }

        $clando = Clando::where('ref', $request->ref)
            ->where('id_agent', $request->id_user)
            ->first();

        if (! $clando) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable']);
        }

        $clando->update(['payment_method' => $request->payment_method]);

        return response()->json(['response' => 200]);
    }
```

par :

```php
    public function setPaymentMethodClando(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        if (! in_array($request->payment_method, ['LIVRAISON', 'OM'], true)) {
            return response()->json(['response' => 400, 'message' => 'Mode de paiement invalide']);
        }

        $clando = Clando::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $clando) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable']);
        }

        $clando->update(['payment_method' => $request->payment_method]);

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 4: Modifier `OrderController::setPaymentMethodOrder`**

Remplacer :

```php
    public function setPaymentMethodOrder(Request $request)
    {
        if (! in_array($request->payment_method, ['LIVRAISON', 'OM'], true)) {
            return response()->json(['response' => 400, 'message' => 'Mode de paiement invalide']);
        }

        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $request->id_user)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        $order->update(['payment_method' => $request->payment_method]);

        return response()->json(['response' => 200]);
    }
```

par :

```php
    public function setPaymentMethodOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        if (! in_array($request->payment_method, ['LIVRAISON', 'OM'], true)) {
            return response()->json(['response' => 400, 'message' => 'Mode de paiement invalide']);
        }

        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        $order->update(['payment_method' => $request->payment_method]);

        return response()->json(['response' => 200]);
    }
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthPaiementModeTest`
Expected: PASS (3 tests) ou skip selon `agents.id_user`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php app/Http/Controllers/API/OrderController.php tests/Feature/AuthPaiementModeTest.php
git commit -m "feat: setPaymentMethodClando/Order exigent un jeton, dérivent l'identité du jeton"
```

---

## Task 8: Fin de course — `ClandoController::terminatedCourse`

**Files:**
- Modify: `app/Http/Controllers/API/ClandoController.php:751-756` (insertion en tête de méthode seulement — le reste de la méthode, transaction incluse, n'est pas modifié par cette tâche)
- Test: `tests/Feature/AuthTerminatedCourseTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()` (Task 2).

C'est l'endpoint le plus sensible de ce plan (crédite `agents.deposit_recu` et `App\Support\LivreDeComptes`) — la vérification d'authentification et de propriété doit être insérée **avant** tout ce bloc financier, pour que les tests `401`/`403` de ce plan n'en dépendent jamais.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthTerminatedCourseTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTerminatedCourseTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?Clando $clando = null;

    protected function tearDown(): void
    {
        $this->clando?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_terminatedCourse_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/terminatedCourse', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_terminatedCourse_avec_un_autre_agent_c_est_403_avant_tout_credit(): void
    {
        if (! Schema::hasTable('clando')) {
            $this->markTestSkipped('Table clando absente de cette base locale.');
        }
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->clando = Clando::create([
            'ref' => 'TEST-TERM-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/terminatedCourse', [
            'token' => $jeton,
            'ref' => $this->clando->ref,
            'payment_method' => 'cash',
        ])->assertOk()->assertJsonPath('response', 403);

        $this->clando->refresh();
        $this->assertSame('process', $this->clando->status, "Un agent non assigné ne doit jamais pouvoir terminer — ni changer le statut, ni déclencher le crédit financier qui suivrait.");
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthTerminatedCourseTest`
Expected: FAIL sur `test_terminatedCourse_sans_jeton_c_est_401` (pas d'authentification aujourd'hui) ; le second test skip si `clando` est absente localement (attendu, voir Global Constraints) — sinon échoue aussi (`assertSame('process', ...)`, aucune vérification de propriété actuellement).

- [ ] **Step 3: Modifier `ClandoController::terminatedCourse`**

Dans `app/Http/Controllers/API/ClandoController.php`, le tout début de la méthode (avant la transaction avec verrou qui suit, non reproduite ici — elle n'est pas modifiée par ce step) est :

```php
       public function terminatedCourse(Request $request)
    {
         $clando = Clando::where('ref',$request->ref)->first();

         if (! $clando) {
             return response()->json(['response' => 400, 'message' => 'Course introuvable']);
         }
```

Insérer la vérification d'authentification et de propriété **juste après** le bloc `if (! $clando)`, avant tout le reste de la méthode (recalcul de prix, transaction, crédit) :

```php
       public function terminatedCourse(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $clando = Clando::where('ref',$request->ref)->first();

         if (! $clando) {
             return response()->json(['response' => 400, 'message' => 'Course introuvable']);
         }

         if ($clando->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
             return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette course."]);
         }
```

Ne rien changer d'autre dans la méthode : chaque usage plus loin de `$request->id_user` (crédit de `deposit_recu`, écriture dans `LivreDeComptes`, libération de `freeStatus`) continue de désigner la même personne qu'avant — l'agent assigné — puisque la vérification ci-dessus garantit déjà que `$clando->id_agent === $utilisateur->id` (ou que l'appelant est `admin`/`employee_afc`, auquel cas ces lignes créditent l'agent réellement assigné, comportement inchangé par rapport à avant ce plan — voir Task 8 Interfaces). **Ne pas remplacer `$request->id_user` par `$utilisateur->id` plus loin dans cette méthode** : contrairement aux tâches précédentes, ici les deux ne désignent pas forcément la même personne quand l'appelant est `employee_afc`/`admin` agissant pour le compte de l'agent réel — laisser `$request->id_user` intact préserve le comportement de crédit déjà documenté par CLAUDE.md règle 15 (l'app employé envoie son propre id, pas celui de l'agent — ce plan ne change pas cette mécanique financière, seulement qui a le droit d'appeler l'endpoint).

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthTerminatedCourseTest`
Expected: PASS (2 tests) — le second skip si `clando` est absente localement (attendu).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/ClandoController.php tests/Feature/AuthTerminatedCourseTest.php
git commit -m "feat: terminatedCourse exige un jeton et vérifie la propriété avant tout crédit"
```

---

## Task 9: Fin de commande — `OrderController::terminatedCourseOrder`

**Files:**
- Modify: `app/Http/Controllers/API/OrderController.php:1020-1029` (insertion en tête de méthode seulement — le reste de la méthode, transaction incluse, n'est pas modifié par cette tâche)
- Test: `tests/Feature/AuthTerminatedCourseOrderTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `ApiAuthentification::utilisateurOuErreur()`, `ApiAuthentification::estStaff()` (Task 2).

Miroir exact de la Task 8 pour `order_detail`. Contrairement à `terminatedCourse`, cette méthode ne se déclenche que si le code de livraison saisi correspond (`if ($codeSaisi !== null && trim(...) === trim(...))`) — la vérification d'authentification/propriété doit donc se faire **avant même cette comparaison**, pour bloquer un appelant non autorisé avant qu'il ne puisse tenter de deviner le code.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AuthTerminatedCourseOrderTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTerminatedCourseOrderTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_terminatedCourseOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/terminatedCourseOrder', ['ref' => 'REF-INEXISTANTE', 'code' => '0000'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_terminatedCourseOrder_avec_un_autre_agent_c_est_403_meme_avec_le_bon_code(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-TERMO-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
            'delivery_code' => '4242',
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/terminatedCourseOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'code' => '4242',
            'payment_method' => 'LIVRAISON',
        ])->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status, "Même en connaissant le code de livraison, un agent non assigné ne doit jamais pouvoir terminer la commande.");
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=AuthTerminatedCourseOrderTest`
Expected: FAIL sur `test_terminatedCourseOrder_sans_jeton_c_est_401` ; le second échoue (`assertSame('process', ...)`, la commande passerait à `Success` aujourd'hui puisque le code correspond) sauf skip si `agents.id_user` manque.

- [ ] **Step 3: Modifier `OrderController::terminatedCourseOrder`**

Dans `app/Http/Controllers/API/OrderController.php`, le tout début de la méthode (avant la lecture du code de livraison et la transaction qui suivent, non reproduites ici — elles ne sont pas modifiées par ce step) est :

```php
       public function terminatedCourseOrder(Request $request)
    {
        
        
        
         $order = order_detail::where('ref',$request->ref)->first();

         if (! $order) {
             return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
         }
```

Insérer la vérification d'authentification et de propriété juste après le bloc `if (! $order)`, avant le reste (lecture du code de livraison, comparaison, transaction) :

```php
       public function terminatedCourseOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $order = order_detail::where('ref',$request->ref)->first();

         if (! $order) {
             return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
         }

         if ($order->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
             return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
         }
```

Ne rien changer d'autre : comme pour la Task 8, chaque usage plus loin de `$request->id_user` (crédit financier) reste tel quel — ne pas le remplacer par `$utilisateur->id`, pour la même raison (règle 15 CLAUDE.md, comportement de crédit pour `employee_afc` inchangé par ce plan).

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=AuthTerminatedCourseOrderTest`
Expected: PASS (2 tests) ou skip sur le second selon `agents.id_user`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/OrderController.php tests/Feature/AuthTerminatedCourseOrderTest.php
git commit -m "feat: terminatedCourseOrder exige un jeton et vérifie la propriété avant tout crédit"
```

---

## Self-Review Notes (pour l'exécutant)

- Ce plan couvre exactement les endpoints listés dans `ARCHITECTURE.md` §13 sous "Plan 2" (`ClandoController`/`OrderController`/`GeolocalisationController::updateAgentPosition`). Le Plan 3 (profil/finance/panier/kiosk/upload — `UserController`, `FinanceController`, `CartController`, `KioskLockController`, `ProductsController::storeProduct`, `ShopsController::addShop`) reste à écrire séparément.
- Tâches 8 et 9 (`terminatedCourse`/`terminatedCourseOrder`) sont volontairement restées seules (pas groupées avec leur miroir) : ce sont les deux méthodes les plus sensibles financièrement de tout ce plan, elles méritent chacune leur propre revue.
- Ne jamais tenter de "corriger" un skip inattendu par une migration ou un DDL improvisés (voir Global Constraints, et l'incident de la Tâche 3 du Plan 1) — un skip sur ce plan signale un écart de schéma déjà documenté, pas un bug de ce plan.
- `CLAUDE.md` (règle 8) n'est mis à jour qu'après le Plan 3, une fois tous les endpoints protégés — pas avant.
