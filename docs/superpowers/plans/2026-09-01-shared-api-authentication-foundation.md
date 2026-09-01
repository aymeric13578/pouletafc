# Authentification API v1.0 — Fondation (Plan 1/3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire et prouver le mécanisme d'authentification par jeton Sanctum (émission à la connexion, révocation, résolution jeton→utilisateur) qui remplacera la confiance en `id_user`/`id_agent` non vérifié sur l'API `v1.0` — sans encore protéger de nouvel endpoint métier (c'est l'objet des plans 2 et 3).

**Architecture:** Généralisation du mécanisme déjà en production dans `MaBoutiqueController` (jeton Sanctum émis par `User::createToken()`, vérifié via `PersonalAccessToken::findToken()`) dans une classe de support unique `App\Support\ApiAuthentification`, réutilisable par tous les contrôleurs API. `loginDelivery`/`loginEmployee` émettent désormais un jeton comme `login()` le fait déjà. Les jetons sont révoqués sur changement de mot de passe et suppression de compte.

**Tech Stack:** Laravel, Laravel Sanctum (déjà installé, `personal_access_tokens` déjà migrée), PHPUnit (tests sous `tests/Feature`, pas de `RefreshDatabase` dans ce projet — voir Global Constraints).

**Spec:** `docs/superpowers/specs/2026-09-01-shared-api-authentication-design.md`

## Global Constraints

- Le jeton est transmis en paramètre de requête (`token`), jamais en en-tête `Authorization` — cohérent avec `MaBoutiqueController::boutiqueVerifiee()` déjà en production (spec §4).
- Les jetons Sanctum n'expirent pas automatiquement ; ils ne sont invalidés que par révocation explicite (spec §3, non-objectifs).
- Cette base de code n'utilise **pas** `RefreshDatabase`/`DatabaseTransactions` dans ses tests : les tests tournent sur la vraie base configurée et doivent nettoyer eux-mêmes ce qu'ils créent (voir `tests/Feature/DemandeDeRetraitTest.php` pour le pattern de référence). Toujours supprimer en `tearDown()` toute ligne créée en `setUp()`/dans un test.
- Les colonnes non couvertes par une migration trackée existent quand même sur la base réelle (table `users` créée à la main avant le dépôt) — ne pas chercher `role`/`status`/`phone`/`whatsapp` dans les migrations, ils sont bien dans `$fillable` de `App\Models\User`.
- `User::factory()->create()` utilise le mot de passe en clair `'password'` (haché une seule fois via le cast `hashed` du modèle — voir `database/factories/UserFactory.php`) : toujours l'utiliser tel quel dans les tests de connexion plutôt que de fabriquer un hash à la main.
- Enveloppe JSON à respecter partout : `{"response": <code>, "message": "..."}`, jamais un nouveau format.

---

## Task 1: `App\Support\ApiAuthentification` — résolution du jeton vers l'utilisateur réel

**Files:**
- Create: `app/Support/ApiAuthentification.php`
- Test: `tests/Feature/ApiAuthentificationTest.php`

**Interfaces:**
- Produces: `App\Support\ApiAuthentification::utilisateur(Request $request): ?User` — résout le champ `token` de la requête vers l'utilisateur propriétaire, ou `null` si absent/invalide.
- Produces: `App\Support\ApiAuthentification::utilisateurOuErreur(Request $request): User|JsonResponse` — même résolution, mais renvoie directement une réponse JSON `401` (enveloppe standard) au lieu de `null`. Consommé par les plans 2 et 3 pour protéger chaque endpoint métier en une ligne.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/ApiAuthentificationTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiAuthentification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiAuthentificationTest extends TestCase
{
    private ?User $utilisateur = null;

    protected function tearDown(): void
    {
        if ($this->utilisateur) {
            $this->utilisateur->tokens()->delete();
            $this->utilisateur->delete();
        }

        parent::tearDown();
    }

    public function test_un_jeton_valide_resout_vers_son_proprietaire(): void
    {
        $this->utilisateur = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $jeton = $this->utilisateur->createToken('test')->plainTextToken;

        $requete = Request::create('/', 'POST', ['token' => $jeton]);

        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($this->utilisateur));
    }

    public function test_un_jeton_invalide_ne_resout_personne(): void
    {
        $requete = Request::create('/', 'POST', ['token' => 'ceci-n-est-pas-un-jeton']);

        $this->assertNull(app(ApiAuthentification::class)->utilisateur($requete));
    }

    public function test_l_absence_de_jeton_ne_resout_personne(): void
    {
        $requete = Request::create('/', 'POST', []);

        $this->assertNull(app(ApiAuthentification::class)->utilisateur($requete));
    }

    public function test_utilisateurOuErreur_renvoie_l_utilisateur_si_le_jeton_est_valide(): void
    {
        $this->utilisateur = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $jeton = $this->utilisateur->createToken('test')->plainTextToken;

        $requete = Request::create('/', 'POST', ['token' => $jeton]);

        $resultat = app(ApiAuthentification::class)->utilisateurOuErreur($requete);

        $this->assertInstanceOf(User::class, $resultat);
        $this->assertTrue($resultat->is($this->utilisateur));
    }

    public function test_utilisateurOuErreur_renvoie_une_reponse_401_si_le_jeton_est_absent(): void
    {
        $requete = Request::create('/', 'POST', []);

        $resultat = app(ApiAuthentification::class)->utilisateurOuErreur($requete);

        $this->assertInstanceOf(JsonResponse::class, $resultat);
        $this->assertSame(401, $resultat->getData()->response);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=ApiAuthentificationTest`
Expected: FAIL — `Class "App\Support\ApiAuthentification" not found`.

- [ ] **Step 3: Écrire l'implémentation minimale**

Créer `app/Support/ApiAuthentification.php` :

```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Résout le jeton Sanctum envoyé par les applications mobiles (champ `token`,
 * jamais l'en-tête Authorization — voir spec 2026-09-01) vers l'utilisateur
 * réel qui l'a émis. Généralise MaBoutiqueController::boutiqueVerifiee(), qui
 * dupliquait cette même logique pour un seul contrôleur.
 */
class ApiAuthentification
{
    public function utilisateur(Request $request): ?User
    {
        $jeton = PersonalAccessToken::findToken((string) $request->input('token'));

        if (! $jeton || ! $jeton->tokenable instanceof User) {
            return null;
        }

        return $jeton->tokenable;
    }

    public function utilisateurOuErreur(Request $request): User|JsonResponse
    {
        $utilisateur = $this->utilisateur($request);

        if (! $utilisateur) {
            return response()->json([
                'response' => 401,
                'message' => 'Session expirée, reconnectez-vous',
            ]);
        }

        return $utilisateur;
    }
}
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=ApiAuthentificationTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/ApiAuthentification.php tests/Feature/ApiAuthentificationTest.php
git commit -m "feat: résolution jeton Sanctum vers utilisateur (ApiAuthentification)"
```

---

## Task 2: `loginDelivery` émet un jeton Sanctum

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:576-580` (bloc de succès de `loginDelivery`)
- Test: `tests/Feature/JetonSessionMobileTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateur()` (Task 1) — utilisé par le test pour vérifier que le jeton retourné est bien utilisable.
- Produces: la réponse JSON de `POST/GET v1.0/loginDelivery` contient désormais une clé `token` au même niveau que `response`/`message`/`data`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/JetonSessionMobileTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiAuthentification;
use Tests\TestCase;

class JetonSessionMobileTest extends TestCase
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

    public function test_loginDelivery_renvoie_un_jeton_utilisable(): void
    {
        $agent = $this->creerAgent();

        $reponse = $this->postJson('/api/v1.0/loginDelivery', [
            'email' => $agent->email,
            'password' => 'password',
        ]);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $jeton = $reponse->json('token');

        $this->assertNotEmpty($jeton, 'loginDelivery doit renvoyer un jeton.');

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($agent));
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: FAIL — la clé `token` de la réponse JSON est vide/absente (`assertNotEmpty` échoue).

- [ ] **Step 3: Modifier `loginDelivery`**

Dans `app/Http/Controllers/API/UserController.php`, remplacer le bloc de retour succès de `loginDelivery` (actuellement lignes 576-580) :

```php
            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser
            ]);
```

par :

```php
            /*
             | Jeton Sanctum, même mécanisme que UserController::login() —
             | voir App\Support\ApiAuthentification, qui le vérifiera sur
             | chaque endpoint protégé (spec 2026-09-01).
             */
            $token = $seachUser->createToken('agent-mobile')->plainTextToken;

            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser,
                "token" => $token,
            ]);
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/JetonSessionMobileTest.php
git commit -m "feat: loginDelivery émet un jeton Sanctum"
```

---

## Task 3: `loginEmployee` émet un jeton Sanctum

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php` (bloc de succès de `loginEmployee`, juste après le contrôle `status !== "Success"` de cette méthode)
- Modify: `tests/Feature/JetonSessionMobileTest.php` (ajoute un test)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateur()` (Task 1).
- Produces: la réponse JSON de `POST/GET v1.0/loginEmployee` contient désormais une clé `token`.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/JetonSessionMobileTest.php`, une méthode privée et un test (après `creerAgent`) :

```php
    private function creerEmploye(): User
    {
        $employe = User::factory()->create(['role' => 'employee_afc', 'status' => 'Success']);
        $this->utilisateursCrees[] = $employe;

        return $employe;
    }

    public function test_loginEmployee_renvoie_un_jeton_utilisable(): void
    {
        $employe = $this->creerEmploye();

        $reponse = $this->postJson('/api/v1.0/loginEmployee', [
            'email' => $employe->email,
            'password' => 'password',
        ]);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $jeton = $reponse->json('token');

        $this->assertNotEmpty($jeton, 'loginEmployee doit renvoyer un jeton.');

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($employe));
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: `test_loginEmployee_renvoie_un_jeton_utilisable` FAIL, les autres PASS.

- [ ] **Step 3: Modifier `loginEmployee`**

Le bloc de succès de `loginEmployee` (même structure que `loginDelivery`, quelques lignes après la ligne 655 vue plus haut) :

```php
            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser
            ]);
```

devient :

```php
            $token = $seachUser->createToken('employee-mobile')->plainTextToken;

            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser,
                "token" => $token,
            ]);
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/JetonSessionMobileTest.php
git commit -m "feat: loginEmployee émet un jeton Sanctum"
```

---

## Task 4: Révocation des jetons sur changement de mot de passe (`changePassword`)

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:488-490` (bloc de mise à jour de `changePassword`)
- Modify: `tests/Feature/JetonSessionMobileTest.php` (ajoute un test)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateur()` (Task 1).
- Produces: après un appel réussi à `changePassword`, tout jeton émis avant cet appel devient invalide.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/JetonSessionMobileTest.php` :

```php
    public function test_changePassword_revoque_les_jetons_existants(): void
    {
        $agent = $this->creerAgent();
        $agent->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('ancien-mdp')])->save();

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/changePassword', [
            'ref' => $agent->ref,
            'password' => 'ancien-mdp',
            'newpassword' => 'nouveau-mdp',
            'confirmpassword' => 'nouveau-mdp',
        ])->assertOk()->assertJsonPath('response', 200);

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNull($resolu, 'Le jeton émis avant le changement de mot de passe doit être révoqué.');
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: `test_changePassword_revoque_les_jetons_existants` FAIL (`$resolu` n'est pas `null`).

- [ ] **Step 3: Modifier `changePassword`**

Dans `app/Http/Controllers/API/UserController.php`, remplacer :

```php
        $update = User::where('ref', $ref)->update([
            'password' => Hash::make($newpassword),
        ]);

        if ($update) {
```

par :

```php
        $update = User::where('ref', $ref)->update([
            'password' => Hash::make($newpassword),
        ]);

        if ($update) {
            /*
             | Un jeton émis avant ce changement ne doit plus être utilisable
             | après — sinon un jeton volé reste valide malgré la
             | réinitialisation (spec 2026-09-01, §6.3).
             */
            $seachUser->tokens()->delete();

```

(la ligne `if ($update) {` existante reste, seule la ligne `$seachUser->tokens()->delete();` est insérée juste après ; le `return response()->json(...)` qui suivait déjà reste inchangé en dessous).

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/JetonSessionMobileTest.php
git commit -m "feat: changePassword révoque les jetons Sanctum existants"
```

---

## Task 5: Révocation des jetons sur `changePasswordByOtp`

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:779-783` (bloc de mise à jour de `changePasswordByOtp`)
- Modify: `tests/Feature/JetonSessionMobileTest.php` (ajoute un test)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateur()` (Task 1).
- Produces: après un appel réussi à `changePasswordByOtp`, tout jeton émis avant cet appel devient invalide (même garantie que Task 4, autre point d'entrée).

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/JetonSessionMobileTest.php` :

```php
    public function test_changePasswordByOtp_revoque_les_jetons_existants(): void
    {
        $agent = $this->creerAgent();
        $agent->forceFill(['confirmation_code' => '54321'])->save();

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/changePasswordByOtp', [
            'method' => 'email',
            'value' => $agent->email,
            'otp' => '54321',
            'password' => 'nouveau-mdp',
        ])->assertOk()->assertJsonPath('response', 200);

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNull($resolu, 'Le jeton émis avant la réinitialisation OTP doit être révoqué.');
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: `test_changePasswordByOtp_revoque_les_jetons_existants` FAIL.

- [ ] **Step 3: Modifier `changePasswordByOtp`**

Dans `app/Http/Controllers/API/UserController.php`, remplacer :

```php
        if ($seachUser && $password) {
            $seachUser->update([
                'password' => Hash::make($password),
                'confirmation_code' => ""
            ]);

            return response()->json([
```

par :

```php
        if ($seachUser && $password) {
            $seachUser->update([
                'password' => Hash::make($password),
                'confirmation_code' => ""
            ]);

            // Voir Task 4 (changePassword) — même garantie sur cet autre
            // point d'entrée de réinitialisation.
            $seachUser->tokens()->delete();

            return response()->json([
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/JetonSessionMobileTest.php
git commit -m "feat: changePasswordByOtp révoque les jetons Sanctum existants"
```

---

## Task 6: Nettoyage des jetons à la suppression de compte (`deleteUser`)

**Files:**
- Modify: `app/Http/Controllers/API/UserController.php:442-443` (juste avant `$seachUser->purgeAccount();`)
- Modify: `tests/Feature/JetonSessionMobileTest.php` (ajoute un test)

**Interfaces:**
- Consumes: aucune nouvelle (utilise le modèle `User` existant).
- Produces: aucune ligne orpheline dans `personal_access_tokens` après une suppression de compte réussie.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter dans `tests/Feature/JetonSessionMobileTest.php` :

```php
    public function test_deleteUser_supprime_les_jetons_du_compte(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        // Pas ajouté à $utilisateursCrees : purgeAccount() le supprime déjà,
        // un second delete() en tearDown lèverait une erreur sur rien.

        $agent->createToken('agent-mobile');
        $this->assertSame(1, $agent->tokens()->count());

        $this->postJson('/api/v1.0/deleteUser', [
            'ref' => $agent->ref,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $agent->id,
        ]);
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: `test_deleteUser_supprime_les_jetons_du_compte` FAIL (la ligne `personal_access_tokens` existe encore).

- [ ] **Step 3: Modifier `deleteUser`**

Dans `app/Http/Controllers/API/UserController.php`, remplacer :

```php
        try {
            $seachUser->purgeAccount();
        } catch (\Throwable $e) {
```

par :

```php
        try {
            $seachUser->tokens()->delete();
            $seachUser->purgeAccount();
        } catch (\Throwable $e) {
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --filter=JetonSessionMobileTest`
Expected: PASS (5 tests) — `User::purgeAccount()` (app/Models/User.php:126-144) garde déjà chaque table enfant derrière `Schema::hasTable()`/`Schema::hasColumn()`, donc ce test n'a pas besoin de sa propre garde de schéma.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/API/UserController.php tests/Feature/JetonSessionMobileTest.php
git commit -m "feat: deleteUser supprime les jetons Sanctum du compte"
```

---

## Task 7: `MaBoutiqueController::boutiqueVerifiee` réutilise `ApiAuthentification`

**Files:**
- Modify: `app/Http/Controllers/API/MaBoutiqueController.php:42-51`
- Test: `tests/Feature/MaBoutiqueAuthentificationTest.php` (nouveau fichier)

**Interfaces:**
- Consumes: `App\Support\ApiAuthentification::utilisateur()` (Task 1).
- Produces: aucun changement de comportement observable pour les 14 endpoints déjà consommateurs de `boutiqueVerifiee()` — ce test prouve la non-régression.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/MaBoutiqueAuthentificationTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Tests\TestCase;

class MaBoutiqueAuthentificationTest extends TestCase
{
    private ?User $marchand = null;
    private ?Shop $boutique = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Le schéma local de `shops` peut être en retard sur la production
        // (colonnes ajoutées à la main sur le serveur, absentes de la
        // migration trackée — voir Global Constraints). Sans `id_user`, ce
        // test ne peut vérifier rien d'utile.
        if (! \Illuminate\Support\Facades\Schema::hasColumn('shops', 'id_user')) {
            $this->markTestSkipped("Colonne `shops.id_user` absente de cette base : schéma incomplet.");
        }
    }

    protected function tearDown(): void
    {
        $this->boutique?->delete();
        if ($this->marchand) {
            $this->marchand->tokens()->delete();
            $this->marchand->delete();
        }

        parent::tearDown();
    }

    public function test_getMyShop_fonctionne_toujours_avec_un_jeton_valide(): void
    {
        $this->marchand = User::factory()->create(['role' => 'user', 'status' => 'Success']);
        $this->boutique = Shop::create([
            'shop_name' => 'Boutique test authentification ' . uniqid(),
            'id_user' => $this->marchand->id,
            'status' => 'Success',
        ]);

        $jeton = $this->marchand->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/getMyShop?token=' . $jeton)
            ->assertOk()
            ->assertJsonPath('response', 200);
    }

    public function test_getMyShop_renvoie_data_null_avec_un_jeton_invalide(): void
    {
        // Comportement actuel et volontaire de getMyShop (ligne 87-91) : un
        // jeton invalide et un compte sans boutique produisent la même
        // réponse "response: 200, data: null" — l'application masque
        // simplement l'entrée boutique, sans distinguer les deux cas.
        $this->getJson('/api/v1.0/getMyShop?token=jeton-invalide')
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data', null);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il passe déjà (comportement actuel)**

Run: `php artisan test --filter=MaBoutiqueAuthentificationTest`
Expected: PASS — ce test décrit le comportement **actuel** de `boutiqueVerifiee()` (pas encore modifié). C'est le filet de sécurité de la régression pour l'étape suivante, pas un test qui doit échouer avant l'implémentation (Task 7 est un refactor, pas une nouvelle fonctionnalité).

- [ ] **Step 3: Refactoriser `boutiqueVerifiee`**

Dans `app/Http/Controllers/API/MaBoutiqueController.php`, remplacer :

```php
    private function boutiqueVerifiee(Request $request): ?Shop
    {
        $jeton = PersonalAccessToken::findToken((string) $request->input('token'));

        if (! $jeton || ! $jeton->tokenable) {
            return null;
        }

        return Shop::where('id_user', $jeton->tokenable_id)->first();
    }
```

par :

```php
    private function boutiqueVerifiee(Request $request): ?Shop
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateur($request);

        if (! $utilisateur) {
            return null;
        }

        return Shop::where('id_user', $utilisateur->id)->first();
    }
```

Retirer l'import devenu inutile `use Laravel\Sanctum\PersonalAccessToken;` en tête de fichier s'il n'est plus référencé ailleurs dans la classe (vérifier avec `grep -n PersonalAccessToken app/Http/Controllers/API/MaBoutiqueController.php` avant de le retirer).

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe toujours**

Run: `php artisan test --filter=MaBoutiqueAuthentificationTest`
Expected: PASS (2 tests, comportement inchangé).

- [ ] **Step 5: Lancer la suite complète pour vérifier l'absence de régression**

Run: `php artisan test`
Expected: aucun nouvel échec par rapport à l'état de la base avant ce plan (43 échecs préexistants sans lien, déjà documentés dans `TASKS.md` du 2026-08-30 — ne pas chercher à les corriger ici).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/API/MaBoutiqueController.php tests/Feature/MaBoutiqueAuthentificationTest.php
git commit -m "refactor: MaBoutiqueController réutilise ApiAuthentification"
```

---

## Self-Review Notes (pour l'exécutant)

- Ce plan ne protège **aucun** endpoint métier (position, retrait, prise de course...) — c'est volontaire, voir spec §11 et la décision de découpage en 3 plans. Ne pas ajouter de vérification de jeton ailleurs que dans les 7 tâches ci-dessus sans reprendre la spec.
- Les plans 2 (courses/commandes — `ClandoController`/`OrderController`, logique financière) et 3 (profil/finance/panier/kiosk/upload) restent à écrire séparément une fois ce plan exécuté et revu.
- `CLAUDE.md` (règle 8) et `ARCHITECTURE.md` ne sont **pas** mis à jour par ce plan : ils documentent l'état réel du code, qui ne change vraiment qu'une fois les 3 plans terminés (spec §11, étape 5).
