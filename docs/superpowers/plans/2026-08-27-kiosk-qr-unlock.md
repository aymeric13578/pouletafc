# Verrou QR pour les écrans "mur" — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer l'accès libre des 3 écrans "mur" sans authentification (`/commandes`, `/clando`, `/commandes/carte`) par un verrou QR : l'écran affiche un QR tant que personne ne l'a scanné, un employé le débloque depuis l'appli, le déblocage tient jusqu'à minuit.

**Architecture:** Un jeton aléatoire par écran (table `kiosk_unlock_tokens`), affiché en QR par une page React/Inertia dédiée qui sonde le serveur toutes les 2s ; l'appli employé scanne ce QR et appelle un nouvel endpoint `v1.0` qui marque le jeton déverrouillé ; le serveur pose alors un cookie signé (minuit) que les 3 contrôleurs vérifient avant de rendre leur page normale.

**Tech Stack:** Laravel 10 (PHP), Inertia + React + Vite (front dashboard), Flutter (`empolyeeafc`, package `mobile_scanner`).

**Spec:** `docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md`

## Global Constraints

- **Pas de suite de tests automatisés pour cette catégorie de code** — aucun test PHPUnit n'existe pour les classes `Support` comparables (`Idempotence`, `AttributionAgent`, etc.). Chaque tâche backend se vérifie via `php artisan tinker` et/ou `curl`, pas en écrivant une suite de tests isolée qui n'aurait pas d'équivalent ailleurs dans ce module. Les tâches mobiles se vérifient via `flutter analyze` puis `flutter run` sur appareil physique (voir `run` skill).
- **Correction par rapport à la spec initiale** : les 3 pages concernées rendent déjà via `Inertia::render(...)` vers des composants React sous `resources/js/Pages/...` (pas des vues Blade classiques). L'écran de verrouillage est donc un composant React `Kiosk/Lock.jsx`, pas une vue Blade — le principe (QR, sondage, cookie) ne change pas.
- **Verbe HTTP** : le nouvel endpoint mobile `deverrouillerEcranKiosk` est enregistré en GET **et** POST vers la même méthode (CLAUDE.md règle 2). Le client mobile l'appelle en GET avec des paramètres de requête (`?token=...&id_user=...`), comme `takeOrderCommand`/`declinOrderCommand` déjà dans ce fichier — pas de corps JSON, pour rester cohérent avec le reste de l'appli.
- **Convention de réponse JSON** : tout endpoint `v1.0` renvoie toujours HTTP 200, le vrai statut vivant dans le champ JSON `response` (voir `App\Support\Idempotence`, déjà dans ce backend) — ne jamais faire dépendre une vérification du code de statut HTTP.
- **Nommage des pages** : la clé `page` utilisée partout (table, cookie, route) prend l'une de ces 3 valeurs exactes : `commandes`, `clando`, `commandes_carte`.
- **Aucune vérification de rôle sur `id_user`** dans cette passe (cohérent avec CLAUDE.md règle 8 — l'API v1.0 n'authentifie jamais) — documenté comme limitation connue, pas contourné en silence.

---

## Task 1: Table et modèle `KioskUnlockToken`

**Files:**
- Create: `database/migrations/2026_08_27_000002_creer_table_kiosk_unlock_tokens.php`
- Create: `app/Models/KioskUnlockToken.php`

**Interfaces:**
- Produces: `KioskUnlockToken` (Eloquent model, table `kiosk_unlock_tokens`), colonnes `page` (string), `token` (string, unique), `expires_at` (datetime), `unlocked_at` (datetime nullable), `unlocked_by_user_id` (int nullable).

- [ ] **Step 1: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons de déverrouillage des écrans "mur" sans authentification
 * (/commandes, /clando, /commandes/carte) — voir App\Support\KioskLock et
 * docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kiosk_unlock_tokens')) {
            return;
        }

        Schema::create('kiosk_unlock_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('page', 30);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedBigInteger('unlocked_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['page', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_unlock_tokens');
    }
};
```

Fichier à créer : `database/migrations/2026_08_27_000002_creer_table_kiosk_unlock_tokens.php`

- [ ] **Step 2: Lancer la migration en local**

Run: `php artisan migrate`
Expected: `2026_08_27_000002_creer_table_kiosk_unlock_tokens ... DONE` dans la sortie.

- [ ] **Step 3: Écrire le modèle**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jeton de déverrouillage d'un écran "mur" — voir App\Support\KioskLock et la
 * migration 2026_08_27_000002_creer_table_kiosk_unlock_tokens.
 */
class KioskUnlockToken extends Model
{
    protected $table = 'kiosk_unlock_tokens';

    protected $fillable = [
        'page',
        'token',
        'expires_at',
        'unlocked_at',
        'unlocked_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];
}
```

Fichier à créer : `app/Models/KioskUnlockToken.php`

- [ ] **Step 4: Vérifier via tinker**

Run: `php artisan tinker --execute="dump(App\Models\KioskUnlockToken::create(['page' => 'commandes', 'token' => \Illuminate\Support\Str::random(48), 'expires_at' => now()->addMinutes(10)])->toArray());"`

Note : `Str` doit être qualifié en entier (`\Illuminate\Support\Str`) dans ce contexte `--execute`, qui s'exécute en namespace global sans alias — contrairement à `now()` (helper global) ou `App\Models\...` (déjà qualifié).
Expected: un tableau affiché avec `page: "commandes"`, un `token` de 48 caractères, `unlocked_at: null`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_27_000002_creer_table_kiosk_unlock_tokens.php app/Models/KioskUnlockToken.php
git commit -m "feat: table et modèle des jetons de déverrouillage kiosk"
```

---

## Task 2: Support `KioskLock`

**Files:**
- Create: `app/Support/KioskLock.php`

**Interfaces:**
- Consumes: `KioskUnlockToken` (Task 1).
- Produces: `KioskLock::estDeverrouille(Request $request, string $page): bool`, `KioskLock::jetonActif(string $page): KioskUnlockToken`, `KioskLock::poserCookie(string $page): void`, `KioskLock::nomCookie(string $page): string` — utilisés par les Tasks 3, 4 et 6.

- [ ] **Step 1: Écrire la classe**

```php
<?php

namespace App\Support;

use App\Models\KioskUnlockToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Verrou QR pour les écrans "mur" sans authentification (/commandes, /clando,
 * /commandes/carte — voir routes/shop.php et le commentaire qui documente le
 * risque). Un écran affiche un QR à la place du contenu tant qu'aucun employé
 * ne l'a scanné depuis l'appli ; une fois débloqué, un cookie signé Laravel
 * (EncryptCookies, infalsifiable sans APP_KEY) fait passer le contenu jusqu'à
 * minuit. Voir docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 */
class KioskLock
{
    private const DUREE_JETON_MINUTES = 10;

    public static function nomCookie(string $page): string
    {
        return 'kiosk_unlock_' . $page;
    }

    public static function estDeverrouille(Request $request, string $page): bool
    {
        return $request->cookie(self::nomCookie($page)) === '1';
    }

    /**
     * Jeton affichable pour cette page : réutilise le dernier jeton encore
     * valide (pas expiré, pas déjà scanné) s'il existe, en crée un sinon —
     * évite de régénérer un nouveau QR à chaque sondage du navigateur.
     */
    public static function jetonActif(string $page): KioskUnlockToken
    {
        $jeton = KioskUnlockToken::where('page', $page)
            ->whereNull('unlocked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($jeton) {
            return $jeton;
        }

        return KioskUnlockToken::create([
            'page' => $page,
            'token' => Str::random(48),
            'expires_at' => now()->addMinutes(self::DUREE_JETON_MINUTES),
        ]);
    }

    public static function poserCookie(string $page): void
    {
        $minutesJusquaMinuit = (int) now()->diffInMinutes(now()->endOfDay()->addSecond());

        Cookie::queue(self::nomCookie($page), '1', $minutesJusquaMinuit);
    }
}
```

Fichier à créer : `app/Support/KioskLock.php`

- [ ] **Step 2: Vérifier via tinker**

Run: `php artisan tinker --execute="dump(App\Support\KioskLock::jetonActif('commandes')->token);"`
Expected: une chaîne de 48 caractères affichée. Relancer la même commande : le même jeton doit revenir (réutilisation), pas un nouveau.

- [ ] **Step 3: Commit**

```bash
git add app/Support/KioskLock.php
git commit -m "feat: classe KioskLock (jeton actif, cookie de déverrouillage)"
```

---

## Task 3: Endpoint web de sondage

**Files:**
- Create: `app/Http/Controllers/Admin/KioskLockController.php`
- Modify: `routes/shop.php` (ajout après la ligne 115, avant `/download-app`)

**Interfaces:**
- Consumes: `KioskLock` (Task 2), `KioskUnlockToken` (Task 1).
- Produces: route nommée `kiosk.status`, `GET /deverrouillage/{token}/statut` → JSON `{unlocked: bool, expired?: bool}`. Consommée par le composant React de la Task 5.

- [ ] **Step 1: Écrire le contrôleur**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskUnlockToken;
use App\Support\KioskLock;
use Illuminate\Http\JsonResponse;

/**
 * Sondé par les 3 écrans "mur" pendant qu'ils affichent un QR — voir
 * App\Support\KioskLock. Volontairement sans authentification : c'est
 * justement l'écran non authentifié qui interroge ce point pour savoir s'il
 * peut se débloquer.
 */
class KioskLockController extends Controller
{
    public function statut(string $token): JsonResponse
    {
        $jeton = KioskUnlockToken::where('token', $token)->first();

        if (! $jeton) {
            return response()->json(['unlocked' => false, 'expired' => true]);
        }

        if ($jeton->unlocked_at) {
            KioskLock::poserCookie($jeton->page);

            return response()->json(['unlocked' => true]);
        }

        if ($jeton->expires_at->isPast()) {
            return response()->json(['unlocked' => false, 'expired' => true]);
        }

        return response()->json(['unlocked' => false]);
    }
}
```

Fichier à créer : `app/Http/Controllers/Admin/KioskLockController.php`

- [ ] **Step 2: Ajouter la route**

Dans `routes/shop.php`, insérer juste avant la ligne `Route::get('/download-app', ...)` (actuellement ligne 117) :

```php
/*
| Sondé par les écrans "mur" (commandes/clando/carte) pendant qu'ils
| affichent un QR de déverrouillage — voir App\Support\KioskLock.
*/
Route::get('/deverrouillage/{token}/statut', [\App\Http\Controllers\Admin\KioskLockController::class, 'statut'])->name('kiosk.status');
```

- [ ] **Step 3: Vérifier avec curl contre le serveur local**

Run: `php artisan serve` (dans un terminal séparé), puis :
```bash
TOKEN=$(php artisan tinker --execute="echo App\Support\KioskLock::jetonActif('commandes')->token;")
curl -s "http://127.0.0.1:8000/deverrouillage/$TOKEN/statut"
```
Expected: `{"unlocked":false}`

Puis simuler un scan et revérifier :
```bash
php artisan tinker --execute="App\Models\KioskUnlockToken::where('token','$TOKEN')->update(['unlocked_at' => now(), 'unlocked_by_user_id' => 1]);"
curl -sD - "http://127.0.0.1:8000/deverrouillage/$TOKEN/statut"
```
Expected: corps `{"unlocked":true}` et un en-tête `Set-Cookie: kiosk_unlock_commandes=...`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/KioskLockController.php routes/shop.php
git commit -m "feat: endpoint de sondage du déverrouillage kiosk"
```

---

## Task 4: Endpoint mobile de déverrouillage

**Files:**
- Create: `app/Http/Controllers/API/KioskLockController.php`
- Modify: `routes/api.php` (ajout dans le groupe `v1.0`)

**Interfaces:**
- Consumes: `KioskUnlockToken` (Task 1).
- Produces: route `deverrouillerEcranKiosk` (GET + POST) → JSON `{response: 200|404|409, message: string}`. Consommée par l'appli mobile (Task 8).

- [ ] **Step 1: Écrire le contrôleur**

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\KioskUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Déverrouillage d'un écran "mur" depuis l'appli employé (scan du QR affiché
 * à l'écran) — voir App\Support\KioskLock. Même convention que le reste de
 * l'API v1.0 (règle 8, CLAUDE.md) : id_user n'est pas vérifié comme étant
 * réellement un compte employee_afc/admin, seulement mémorisé pour l'audit.
 */
class KioskLockController extends Controller
{
    public function deverrouiller(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'token' => ['required', 'string'],
            'id_user' => ['required', 'integer'],
        ]);

        $jeton = KioskUnlockToken::where('token', $valide['token'])->first();

        if (! $jeton || $jeton->expires_at->isPast()) {
            return response()->json([
                'response' => 404,
                'message' => "Ce QR code n'est plus valide, ouvre l'écran pour en afficher un nouveau.",
            ]);
        }

        if ($jeton->unlocked_at) {
            return response()->json([
                'response' => 409,
                'message' => 'Cet écran a déjà été déverrouillé.',
            ]);
        }

        $jeton->update([
            'unlocked_at' => now(),
            'unlocked_by_user_id' => $valide['id_user'],
        ]);

        return response()->json([
            'response' => 200,
            'message' => 'Écran déverrouillé.',
        ]);
    }
}
```

Fichier à créer : `app/Http/Controllers/API/KioskLockController.php`

- [ ] **Step 2: Ajouter la route dans le groupe v1.0**

Dans `routes/api.php`, à l'intérieur du `Route::group(['namespace' => 'App\Http\Controllers\API','prefix'=>'v1.0'], function () { ... })`, ajouter :

```php
// Déverrouillage d'un écran "mur" depuis l'appli employé (scan de QR).
Route::get('deverrouillerEcranKiosk', 'KioskLockController@deverrouiller')
    ->middleware('throttle:20,1')
    ->name('deverrouillerEcranKiosk.get');
Route::post('deverrouillerEcranKiosk', 'KioskLockController@deverrouiller')
    ->middleware('throttle:20,1')
    ->name('deverrouillerEcranKiosk.post');
```

- [ ] **Step 3: Vérifier avec curl**

```bash
TOKEN=$(php artisan tinker --execute="echo App\Support\KioskLock::jetonActif('clando')->token;")
curl -s "http://127.0.0.1:8000/api/v1.0/deverrouillerEcranKiosk?token=$TOKEN&id_user=1"
```
Expected: `{"response":200,"message":"\u00c9cran d\u00e9verrouill\u00e9."}`

Rejouer la même commande :
Expected: `{"response":409,"message":"Cet \u00e9cran a d\u00e9j\u00e0 \u00e9t\u00e9 d\u00e9verrouill\u00e9."}`

Avec un jeton inventé :
```bash
curl -s "http://127.0.0.1:8000/api/v1.0/deverrouillerEcranKiosk?token=inexistant&id_user=1"
```
Expected: `{"response":404,...}`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/API/KioskLockController.php routes/api.php
git commit -m "feat: endpoint mobile de déverrouillage des écrans kiosk"
```

---

## Task 5: Écran React "verrouillé"

**Files:**
- Create: `resources/js/Pages/Kiosk/Lock.jsx`
- Modify: `package.json` (ajout de `qrcode`)

**Interfaces:**
- Consumes: route `kiosk.status` (Task 3, appelée en `axios.get`).
- Produces: composant Inertia `Kiosk/Lock`, props `{page: string, token: string}` — rendu par les 3 contrôleurs de la Task 6.

- [ ] **Step 1: Installer la dépendance QR**

Run: `npm install qrcode`
Expected: `qrcode` apparaît dans `"dependencies"` de `package.json`.

- [ ] **Step 2: Écrire le composant**

```jsx
import { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import QRCode from 'qrcode';

/*
 * Écran affiché à la place du mur des commandes / de la carte clando / de la
 * carte des livraisons tant que l'écran n'a pas été débloqué par un employé —
 * voir App\Support\KioskLock et
 * docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md.
 *
 * Le jeton passé en prop n'appartient qu'à ce chargement de page : s'il
 * expire avant d'être scanné (10 min), le sondage le détecte et recharge la
 * page, qui obtient alors un jeton neuf côté serveur.
 */
const INTERVALLE_SONDAGE_MS = 2000;

const TITRES = {
    commandes: 'Mur des commandes verrouillé',
    clando: 'Carte des courses verrouillée',
    commandes_carte: 'Carte des livraisons verrouillée',
};

export default function Lock({ page, token }) {
    const canvasRef = useRef(null);
    const [erreur, setErreur] = useState(null);

    useEffect(() => {
        if (canvasRef.current) {
            QRCode.toCanvas(canvasRef.current, token, { width: 280, margin: 2 });
        }
    }, [token]);

    useEffect(() => {
        const intervalle = setInterval(async () => {
            try {
                const { data } = await axios.get(`/deverrouillage/${token}/statut`);
                if (data.unlocked || data.expired) {
                    window.location.reload();
                }
                setErreur(null);
            } catch (e) {
                setErreur('Connexion au serveur interrompue, nouvelle tentative...');
            }
        }, INTERVALLE_SONDAGE_MS);

        return () => clearInterval(intervalle);
    }, [token]);

    return (
        <>
            <Head title="Écran verrouillé" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-slate-900 text-white">
                <h1 className="mb-2 text-2xl font-semibold">
                    {TITRES[page] ?? 'Écran verrouillé'}
                </h1>
                <p className="mb-8 text-slate-300">
                    Scanne ce QR code avec l'application employé pour afficher cet écran.
                </p>
                <div className="rounded-2xl bg-white p-6">
                    <canvas ref={canvasRef} />
                </div>
                {erreur && <p className="mt-6 text-sm text-amber-400">{erreur}</p>}
            </div>
        </>
    );
}
```

Fichier à créer : `resources/js/Pages/Kiosk/Lock.jsx`

- [ ] **Step 3: Vérifier que le build passe**

Run: `npm run build`
Expected: build Vite qui se termine sans erreur, `Kiosk/Lock.jsx` inclus dans la sortie.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Kiosk/Lock.jsx package.json package-lock.json
git commit -m "feat: écran React de verrouillage QR des murs kiosk"
```

---

## Task 6: Brancher le verrou dans les 3 contrôleurs existants

**Files:**
- Modify: `app/Http/Controllers/Admin/OrderBoardController.php:60-65`
- Modify: `app/Http/Controllers/Admin/ClandoBoardController.php:77-82`
- Modify: `app/Http/Controllers/Admin/OrderMapController.php:65-70`

**Interfaces:**
- Consumes: `KioskLock::estDeverrouille()`, `KioskLock::jetonActif()` (Task 2), composant `Kiosk/Lock` (Task 5).

- [ ] **Step 1: `OrderBoardController::index`**

Ajouter `use App\Support\KioskLock;` en haut du fichier, puis remplacer :

```php
public function index(Request $request): Response
{
    return Inertia::render('Orders/Board', [
        'initial' => $this->payload($request),
    ]);
}
```

par :

```php
public function index(Request $request): Response
{
    if (! KioskLock::estDeverrouille($request, 'commandes')) {
        return Inertia::render('Kiosk/Lock', [
            'page' => 'commandes',
            'token' => KioskLock::jetonActif('commandes')->token,
        ]);
    }

    return Inertia::render('Orders/Board', [
        'initial' => $this->payload($request),
    ]);
}
```

- [ ] **Step 2: `ClandoBoardController::index`**

Même ajout d'import, puis remplacer :

```php
public function index(Request $request): Response
{
    return Inertia::render('Clando/Board', [
        'initial' => $this->payload($request),
    ]);
}
```

par :

```php
public function index(Request $request): Response
{
    if (! KioskLock::estDeverrouille($request, 'clando')) {
        return Inertia::render('Kiosk/Lock', [
            'page' => 'clando',
            'token' => KioskLock::jetonActif('clando')->token,
        ]);
    }

    return Inertia::render('Clando/Board', [
        'initial' => $this->payload($request),
    ]);
}
```

- [ ] **Step 3: `OrderMapController::index`**

Même ajout d'import, puis remplacer :

```php
public function index(Request $request): Response
{
    return Inertia::render('Orders/Map', [
        'initial' => $this->payload($request),
    ]);
}
```

par :

```php
public function index(Request $request): Response
{
    if (! KioskLock::estDeverrouille($request, 'commandes_carte')) {
        return Inertia::render('Kiosk/Lock', [
            'page' => 'commandes_carte',
            'token' => KioskLock::jetonActif('commandes_carte')->token,
        ]);
    }

    return Inertia::render('Orders/Map', [
        'initial' => $this->payload($request),
    ]);
}
```

- [ ] **Step 4: Vérifier dans un navigateur (Playwright ou manuel)**

Run le serveur local (`php artisan serve` + `npm run dev`), ouvrir `http://127.0.0.1:8000/commandes` **sans cookie** (navigation privée) :
Expected: le QR s'affiche, pas le tableau des commandes.

Débloquer via tinker (comme Task 3 Step 3), attendre ≤2s :
Expected: la page se recharge seule et affiche le mur des commandes normal.

Répéter pour `/clando` et `/commandes/carte`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/OrderBoardController.php app/Http/Controllers/Admin/ClandoBoardController.php app/Http/Controllers/Admin/OrderMapController.php
git commit -m "feat: verrou QR branché sur les 3 écrans mur"
```

---

## Task 7: Dépendance mobile et permission caméra

**Files:**
- Modify: `pubspec.yaml` (empolyeeafc)
- Modify: `android/app/src/main/AndroidManifest.xml:8-9` (empolyeeafc)

**Interfaces:**
- Produces: package `mobile_scanner` disponible, permission `CAMERA` déclarée — requis par la Task 8.

- [ ] **Step 1: Ajouter la dépendance**

Depuis `C:\dev\empolyeeafc`, run: `flutter pub add mobile_scanner`
Expected: `mobile_scanner: ^x.y.z` ajouté sous `dependencies:` dans `pubspec.yaml`.

- [ ] **Step 2: Ajouter la permission caméra**

Dans `android/app/src/main/AndroidManifest.xml`, juste après la ligne `<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />` (ligne 8), ajouter :

```xml

    <!-- Scanner QR de déverrouillage des écrans mur — voir
         lib/screens/kiosk_scanner/kiosk_scanner_screen.dart. -->
    <uses-permission android:name="android.permission.CAMERA" />
```

- [ ] **Step 3: Vérifier**

Run: `flutter pub get`
Expected: résolution sans erreur.

`empolyeeafc` est bien un dépôt git, mais la convention établie cette session est de ne committer les changements Dart que si l'utilisateur le demande explicitement — sinon on se limite au test en conditions réelles (`flutter run`). Pas de commit ici sauf demande explicite ; passer directement à la Task 8.

---

## Task 8: Écran scanner

**Files:**
- Create: `lib/screens/kiosk_scanner/kiosk_scanner_screen.dart` (empolyeeafc)

**Interfaces:**
- Consumes: `mobile_scanner` (Task 7), `SessionManager().get('user_id')` (déjà utilisé partout dans l'appli), endpoint `deverrouillerEcranKiosk` (Task 4).
- Produces: `KioskScannerScreen` (StatefulWidget) — utilisé par la Task 9.

- [ ] **Step 1: Écrire l'écran**

```dart
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_session_manager/flutter_session_manager.dart';
import 'package:http/http.dart' as http;
import 'package:mobile_scanner/mobile_scanner.dart';

/// Onglet "Scanner" (ex-"Clando") de la barre de navigation — voir
/// docs/superpowers/specs/2026-08-27-kiosk-qr-unlock-design.md. Les courses
/// clando restent accessibles depuis la carte stat "Clando" de l'accueil ;
/// cet onglet ne fait plus que déverrouiller les écrans muraux du dashboard.
class KioskScannerScreen extends StatefulWidget {
  const KioskScannerScreen({super.key});

  @override
  State<KioskScannerScreen> createState() => _KioskScannerScreenState();
}

class _KioskScannerScreenState extends State<KioskScannerScreen> {
  final MobileScannerController _controller = MobileScannerController();
  String? _dernierToken;
  DateTime? _dernierScanA;
  String? _message;
  bool _succes = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (capture.barcodes.isEmpty) return;
    final token = capture.barcodes.first.rawValue;
    if (token == null || token.isEmpty) return;

    final maintenant = DateTime.now();
    if (_dernierToken == token &&
        _dernierScanA != null &&
        maintenant.difference(_dernierScanA!) < const Duration(seconds: 2)) {
      return;
    }
    _dernierToken = token;
    _dernierScanA = maintenant;

    try {
      final idUser = await SessionManager().get('user_id');
      final reponse = await http.get(Uri.parse(
          'https://pouletafc.com/api/v1.0/deverrouillerEcranKiosk?token=$token&id_user=$idUser'));
      final json = jsonDecode(reponse.body);
      final estSucces = json['response'] == 200;

      if (!mounted) return;
      setState(() {
        _succes = estSucces;
        _message =
            estSucces ? 'Écran déverrouillé.' : "Ce QR code n'est plus valide.";
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _succes = false;
        _message = 'Connexion impossible, réessaie.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          Positioned(
            top: 48,
            left: 16,
            right: 16,
            child: Text(
              "Scanne le QR affiché sur l'écran à déverrouiller",
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                fontFamily: 'Poppins',
                fontWeight: FontWeight.w600,
                fontSize: 16,
                shadows: const [Shadow(blurRadius: 6, color: Colors.black)],
              ),
            ),
          ),
          if (_message != null)
            Positioned(
              bottom: 32,
              left: 16,
              right: 16,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
                decoration: BoxDecoration(
                  color: _succes ? Colors.green.shade600 : Colors.red.shade600,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  _message!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontFamily: 'Poppins',
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
```

Fichier à créer : `lib/screens/kiosk_scanner/kiosk_scanner_screen.dart`

- [ ] **Step 2: Vérifier l'analyse statique**

Run: `flutter analyze lib/screens/kiosk_scanner/kiosk_scanner_screen.dart`
Expected: aucune ligne `error -` (des `info`/`warning` préexistants dans le reste du projet sont tolérés, conformément à la convention déjà établie cette session).

Pas de commit ici sauf demande explicite de l'utilisateur (convention établie cette session pour les changements Dart) — test manuel via `flutter run` suffit à cette étape.

---

## Task 9: Remplacer l'onglet "Clando" par "Scanner"

**Files:**
- Modify: `lib/screens/tab_screen/tab_screen.dart` (empolyeeafc)

**Interfaces:**
- Consumes: `KioskScannerScreen` (Task 8).
- Produces: onglet de navigation renommé — dernière tâche, testée en conditions réelles sur device.

- [ ] **Step 1: Renommer l'import**

Remplacer :
```dart
import 'package:afc_chicken_employee/screens/clando/clando_screen.dart';
```
par :
```dart
import 'package:afc_chicken_employee/screens/kiosk_scanner/kiosk_scanner_screen.dart';
```

- [ ] **Step 2: Renommer la valeur d'énumération**

Dans l'`enum TabScreenAction` (ligne 27-36), remplacer `delivery` par `scanner` — c'est la seule valeur utilisée uniquement dans ce fichier (vérifié : aucune autre référence à `TabScreenAction.delivery` dans le projet).

- [ ] **Step 3: Mettre à jour `_tabs()`**

Remplacer :
```dart
      {
        "icon": MdiIcons.bike,
        "page": TabScreenAction.delivery,
        "text": "Clando",
      },
```
par :
```dart
      {
        "icon": MdiIcons.qrcodeScan,
        "page": TabScreenAction.scanner,
        "text": "Scanner",
      },
```

- [ ] **Step 4: Mettre à jour `_tabContent()`**

Remplacer :
```dart
    if (_selectedPage == TabScreenAction.delivery) {
      return ClandoScreen();
    }
```
par :
```dart
    if (_selectedPage == TabScreenAction.scanner) {
      return const KioskScannerScreen();
    }
```

- [ ] **Step 5: Vérifier l'analyse statique**

Run: `flutter analyze lib/screens/tab_screen/tab_screen.dart`
Expected: aucune ligne `error -`. Si `MdiIcons.qrcodeScan` n'existe pas dans la version installée du package, l'erreur le dira explicitement — remplacer alors par `MdiIcons.qrcode`.

- [ ] **Step 6: Test sur device physique**

Run: `flutter run -d <serial> --no-hot` (voir la skill `run` pour le driver adb déjà établi cette session).
Vérifier : l'onglet du bas affiché "Scanner" (icône QR) ouvre bien la caméra ; scanner un QR affiché par la Task 6 sur un navigateur ouvre bien le déverrouillage (toast/bandeau succès) ; l'accueil donne toujours accès aux courses clando via sa carte stat.

- [ ] **Step 7: Commit uniquement si l'utilisateur le demande explicitement**

Sinon, en rester au test en conditions réelles de l'étape précédente — convention établie cette session pour les changements côté `empolyeeafc`/Flutter.

```bash
git add lib/screens/tab_screen/tab_screen.dart
git commit -m "feat: onglet Clando remplacé par le scanner de déverrouillage kiosk"
```
