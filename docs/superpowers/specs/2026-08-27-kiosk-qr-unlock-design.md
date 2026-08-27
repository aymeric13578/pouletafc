# Verrou QR pour les écrans "mur" sans authentification

## Contexte

Trois pages web du dashboard tournent en continu, sans session ouverte, sur des écrans physiques (TV/tablette) dans le local :

- `/commandes` — `OrderBoardController::index` — mur des commandes
- `/clando` — `ClandoBoardController::index` — carte des courses clando
- `/commandes/carte` — `OrderMapController::index` — carte des livraisons

Le code documente déjà ce choix et son risque (`routes/shop.php:54-61`, `:89-96`, `:106-111`) : n'importe qui connaissant l'URL voit noms, téléphones, adresses clients, et pour `/clando` la position en direct des agents. La parade suggérée dans le commentaire ("déplacer la route derrière un segment secret") n'a jamais été implémentée.

Objectif : remplacer ce non-mécanisme par un vrai verrou, sans réintroduire de compte/mot de passe sur ces écrans (ils n'ont ni clavier ni utilisateur assis devant) — un employé débloque l'écran depuis son téléphone via l'appli `empolyeeafc`, en scannant un QR code affiché à la place du contenu.

## Principe

Modèle "pairing TV" (comme lier une TV à un compte via QR) :

1. L'écran charge une des 3 pages. S'il n'est pas déjà déverrouillé pour aujourd'hui (cookie signé absent/expiré), la page affiche un QR **à la place du contenu** et sonde le serveur toutes les 2s pour savoir si elle a été déverrouillée entre-temps.
2. Un employé ouvre l'onglet "Scanner" de l'appli employé (ancien onglet "Clando" de la barre de navigation, repensé) et scanne ce QR.
3. L'appli envoie le jeton scanné + l'identifiant de l'employé connecté à un nouvel endpoint `v1.0`.
4. Le serveur marque ce jeton comme déverrouillé par cet employé.
5. Au sondage suivant, l'écran voit le déverrouillage, reçoit un cookie signé Laravel (expire à minuit) et recharge la page — le contenu normal s'affiche.
6. Un autre appareil qui ouvrirait la même URL le même jour (pas le même cookie navigateur) retomberait sur un QR, pas sur les données : le verrou est bien par navigateur/écran, pas un déverrouillage global côté serveur pour la journée.

Le jeton expire au bout de 10 minutes s'il n'est pas scanné ; passé ce délai, l'écran (toujours en sondage) reçoit un jeton neuf et le QR affiché se régénère tout seul — une photo d'un vieux QR ne sert donc à rien.

## Alternatives écartées

- **QR = simple lien ouvert dans le navigateur du téléphone** (pas de scanner dans l'appli) : plus simple côté mobile, mais ne correspond pas à la demande explicite d'un scanner intégré à l'appli employé.
- **WebSocket/temps réel** pour un déverrouillage instantané : aucune infra de ce type n'existe dans ce backend aujourd'hui ; un délai de sondage de 2s pour un déverrouillage qui n'a lieu qu'une fois par jour par écran ne justifie pas cette complexité.

## 1. Modèle de données

Nouvelle table `kiosk_unlock_tokens` (migration `2026_08_27_000002_creer_table_kiosk_unlock_tokens.php`, style `Schema::create` simple comme les autres tables neuves de ce backend) :

```php
Schema::create('kiosk_unlock_tokens', function (Blueprint $t) {
    $t->id();
    $t->string('page'); // 'commandes' | 'clando' | 'commandes_carte'
    $t->string('token', 64)->unique();
    $t->timestamp('expires_at');
    $t->timestamp('unlocked_at')->nullable();
    $t->unsignedBigInteger('unlocked_by_user_id')->nullable();
    $t->timestamps();
    $t->index(['page', 'unlocked_at']);
});
```

Pas de table d'audit séparée : `unlocked_by_user_id` + `unlocked_at` suffisent, une ligne par tentative de déverrouillage (les anciennes lignes expirées/non utilisées restent en base, purge non traitée ici — volume négligeable, quelques lignes par jour et par écran).

## 2. Backend

### 2.1 Nouveau support `KioskLock`

`app/Support/KioskLock.php` — logique partagée par les 3 contrôleurs :

- `KioskLock::estDeverrouille(Request $request, string $page): bool` — vérifie le cookie signé `kiosk_unlock_{page}`.
- `KioskLock::jetonActif(string $page): KioskUnlockToken` — retourne le jeton non expiré/non utilisé le plus récent pour cette page, ou en crée un nouveau (token aléatoire 48 caractères, `expires_at` = +10 min).
- `KioskLock::poserCookie(): void` — `Cookie::queue()` un cookie signé (chiffrement Laravel par défaut, donc infalsifiable sans `APP_KEY`) expirant à minuit.

### 2.2 Contrôleurs existants — un seul point d'ajout par `index()`

Dans `OrderBoardController::index`, `ClandoBoardController::index`, `OrderMapController::index` :

```php
if (! KioskLock::estDeverrouille($request, 'commandes')) {
    return view('pages.dashboard.kiosk-lock', [
        'page' => 'commandes',
        'token' => KioskLock::jetonActif('commandes')->token,
    ]);
}
// ... affichage normal existant, inchangé
```

Aucun changement aux routes `/flux` (déjà consommées uniquement une fois l'écran affiché, donc déjà "derrière" le verrou en pratique).

### 2.3 Nouvelle vue `pages/dashboard/kiosk-lock.blade.php`

Affiche le QR (généré en JS côté client via une librairie déjà inline, pas de dépendance serveur — ou généré côté serveur via une lib QR PHP légère si déjà présente dans le projet, à vérifier à l'implémentation) encodant le jeton, et sonde `GET /deverrouillage/{token}/statut` toutes les 2s. Sur `{unlocked: true}`, le serveur a déjà posé le cookie dans la même réponse (`Cookie::queue`) — le JS fait `window.location.reload()`.

### 2.4 Nouvelles routes web (`routes/shop.php`)

```php
Route::get('/deverrouillage/{token}/statut', [KioskLockController::class, 'statut'])->name('kiosk.status');
```

`KioskLockController::statut($token)` : cherche le jeton, si `unlocked_at` non nul → pose le cookie pour la bonne `page` + retourne `{unlocked: true}` ; sinon si expiré → retourne `{unlocked: false, expired: true}` (le JS redemande alors un jeton frais en rechargeant simplement, puisque `index()` en régénère un) ; sinon `{unlocked: false}`.

### 2.5 Nouvel endpoint mobile `v1.0`

`routes/api.php` :
```php
Route::post('deverrouillerEcranKiosk', 'KioskLockController@deverrouiller');
```

`KioskLockController::deverrouiller(Request $request)` — body `{token, id_user}` :
- Jeton introuvable ou expiré → `{response: 404, message: "Ce QR code n'est plus valide, ouvre l'écran pour en afficher un nouveau."}`
- Déjà déverrouillé → `{response: 409, message: "Cet écran a déjà été déverrouillé."}`
- Sinon → `unlocked_at = now()`, `unlocked_by_user_id = id_user`, retourne `{response: 200, message: "Écran déverrouillé."}`

Throttle sur cette route (`throttle:20,1`) — les jetons sont aléatoires mais rien n'empêche une tentative répétée, et contrairement à la convention "pas d'auth" du reste de l'API v1.0 (rule 8), le rate-limiting est orthogonal à l'identité et se justifie ici indépendamment.

Pas de vérification que `id_user` est bien un compte `employee_afc`/`admin` dans cette première version — cohérent avec le reste de l'API v1.0 qui ne vérifie jamais l'identité réelle derrière un `id_user` fourni (rule 8) ; à documenter comme limitation connue plutôt que contournée en silence.

## 3. Mobile (`empolyeeafc`)

### 3.1 Onglet "Clando" → onglet "Scanner"

`lib/screens/tab_screen/tab_screen.dart` : remplace l'entrée Clando par un nouvel écran `lib/screens/kiosk_scanner/kiosk_scanner_screen.dart`. Les courses clando restent accessibles via la carte stat "Clando" de l'accueil (déjà en place cette session) — pas de perte de fonctionnalité.

### 3.2 `kiosk_scanner_screen.dart` (nouveau)

Utilise `mobile_scanner` (package Flutter maintenu pour la lecture QR/caméra ; à ajouter au `pubspec.yaml`, aucune app sœur ne l'a encore). Sur détection d'un QR :
1. Parse le contenu (jeton brut).
2. Récupère l'`id_user` en cache (`SessionManager`, même pattern que le reste de l'appli).
3. `POST v1.0/deverrouillerEcranKiosk` avec `{token, id_user}`.
4. Affiche un toast succès/erreur selon la réponse ; anti-double-scan simple (ignore les détections successives du même contenu pendant ~2s).

Pas de nouvelle permission Android au-delà de la caméra (`android.permission.CAMERA`, déjà nécessaire pour `mobile_scanner`).

## 4. Sécurité

- Cookie signé par le chiffrement Laravel par défaut (`EncryptCookies` middleware, déjà actif globalement) — un client ne peut pas fabriquer un cookie `kiosk_unlock_commandes=1` valide sans `APP_KEY`.
- Jetons aléatoires (48 caractères), à usage unique (un jeton déjà `unlocked_at` non-nul est refusé pour tout nouveau scan), expiration 10 minutes.
- Rate-limit sur l'endpoint mobile de déverrouillage.
- Limitation assumée (cohérente avec l'existant, rule 8) : rien ne vérifie que l'`id_user` fourni par l'appli est réellement un compte autorisé — n'importe quel `id_user` valide dans `users` peut débloquer un écran. Amélioration possible mais hors scope de cette passe : restreindre aux rôles `employee_afc`/`admin` côté `deverrouiller()`.

## 5. Séquencement

**Lot A — backend seul** : migration, `KioskLock`, `KioskLockController` (web + api), vue `kiosk-lock.blade.php`, branchement dans les 3 `index()`. Vérifiable seul en ouvrant les 3 URLs dans un navigateur et en déverrouillant à la main via tinker (`KioskUnlockToken::first()->update(['unlocked_at' => now()])`) avant même que le mobile existe.

**Lot B — mobile** : `mobile_scanner`, écran scanner, remplacement de l'onglet Clando. Testé en conditions réelles : ouvrir une des 3 pages sur un navigateur, scanner avec le téléphone, vérifier le déverrouillage effectif et la persistance après reload/lendemain (test de la date en changeant l'horloge système si besoin, ou en forçant `expires_at`/cookie en base pour simuler minuit).

## 6. Vérification

- Lot A : Playwright — charger `/commandes` sans cookie → QR visible, pas de données ; débloquer le jeton en base → sondage suivant révèle le contenu ; recharger dans un navigateur *différent* (pas le cookie) le même jour → QR à nouveau affiché.
- Lot B : `flutter run` + adb — scanner un vrai QR affiché sur un écran, vérifier le toast succès et le déverrouillage effectif côté web ; scanner un jeton expiré/déjà utilisé, vérifier les messages d'erreur ; confirmer que l'accueil donne toujours accès aux courses clando après le remplacement de l'onglet.

## Fichiers critiques

- `C:\dev\pouletafc\app\Support\KioskLock.php` (nouveau)
- `C:\dev\pouletafc\app\Models\KioskUnlockToken.php` (nouveau)
- `C:\dev\pouletafc\app\Http\Controllers\Admin\KioskLockController.php` (nouveau, routes web)
- `C:\dev\pouletafc\app\Http\Controllers\API\KioskLockController.php` (nouveau, ou méthode ajoutée à un contrôleur API existant)
- `C:\dev\pouletafc\app\Http\Controllers\Admin\OrderBoardController.php`
- `C:\dev\pouletafc\app\Http\Controllers\Admin\ClandoBoardController.php`
- `C:\dev\pouletafc\app\Http\Controllers\Admin\OrderMapController.php`
- `C:\dev\pouletafc\resources\views\pages\dashboard\kiosk-lock.blade.php` (nouveau)
- `C:\dev\pouletafc\routes\shop.php`
- `C:\dev\pouletafc\routes\api.php`
- `C:\dev\empolyeeafc\lib\screens\kiosk_scanner\kiosk_scanner_screen.dart` (nouveau)
- `C:\dev\empolyeeafc\lib\screens\tab_screen\tab_screen.dart`
- `C:\dev\empolyeeafc\pubspec.yaml`
