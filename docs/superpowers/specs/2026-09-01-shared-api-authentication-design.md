# Authentification partagée pour l'API `v1.0` — design

Date : 2026-09-01
Statut : validé par l'utilisateur (brainstorming), prêt pour plan d'implémentation.
Dépôts concernés : `pouletafc` (backend), `plouletafcapp`, `pouletafc_agent`, `empolyeeafc`.

## 1. Contexte et problème

L'API mobile `v1.0` de `pouletafc` n'a aucune authentification (CLAUDE.md règle 8) :
tout `id_user`/`id_agent` reçu en paramètre est utilisé tel quel côté serveur, sans
vérifier qu'il correspond réellement à l'appelant. Un audit de sécurité complet
(2026-09-01, voir `ARCHITECTURE.md` §12 et la conversation associée) a confirmé que
c'est exploitable concrètement : usurpation de position GPS d'un agent
(`updateAgentPosition`), déverrouillage de kiosk avec un `id_user` arbitraire
(`deverrouillerEcranKiosk`), lecture de l'historique financier d'un agent
(`getfinanceAgent`/`getPaymentsAgent`), modification du profil de n'importe qui
(`updateUser`), retrait Orange Money rejouable par simple appel HTTP direct
(`requestWithdrawal`, la vérification biométrique côté app n'a aucun effet côté
serveur), etc.

Le mécanisme n'est pas à inventer : `pouletafc` a déjà Laravel Sanctum installé
(`composer.json`, table `personal_access_tokens` migrée), le modèle `User` utilise
déjà `HasApiTokens`, et `UserController::login()` (connexion client) émet déjà un
jeton (`createToken('client-mobile')->plainTextToken`), vérifié sur les ~14
endpoints de `MaBoutiqueController` via une méthode privée `boutiqueVerifiee()` qui
retrouve le vrai propriétaire (`tokenable_id`) à partir du jeton. Ce projet
généralise ce précédent, qui fonctionne déjà en production, à toute l'API `v1.0`
qui porte une identité ou déclenche une action.

## 2. Objectifs

- Éliminer la classe de faille "IDOR par `id_user`/`id_agent` non vérifié" sur
  tous les endpoints `v1.0` qui portent une identité ou déclenchent une action.
- Vérifier, en plus de l'identité, que l'appelant a le droit d'agir sur la
  ressource précise ciblée (propriété/assignation), pas seulement qu'il est
  connecté.
- Ne rien casser sur les endpoints purement publics (catalogue, liste des
  boutiques...) — ils restent accessibles sans jeton.
- Documenter la réalité du code dans `CLAUDE.md` (règle 8) une fois le travail
  fait, pas avant.

## 3. Non-objectifs (hors périmètre, YAGNI)

- Pas de scoping par "abilities" Sanctum par app (`agent`/`employee`/`client`) —
  les contrôles de rôle déjà en place par endpoint (ex. `loginDelivery` qui
  vérifie `role in [agent, admin]`) suffisent ; à revisiter seulement si un
  besoin concret apparaît.
- Pas d'expiration/refresh de jeton — les jetons Sanctum restent valides jusqu'à
  révocation explicite (déconnexion, changement de mot de passe), cohérent avec
  l'UX actuelle de connexion persistante (règle 20 CLAUDE.md pour `empolyeeafc`,
  comportement implicite ailleurs).
- Pas de garde-fou technique de type "mise à jour obligatoire bloquante" avant
  la bascule backend — décision explicite de l'utilisateur (voir §9), la
  coordination du rollout est opérationnelle, pas dans ce projet.
- Pas de traitement des failles "élevées"/"moyennes" restantes de l'audit
  (secret Mailjet en dur, clé Google Maps en dur, `usesCleartextTraffic`,
  concaténations SQL fragiles, SMS bombing) — hors périmètre de ce design,
  peuvent faire l'objet d'un projet séparé.

## 4. Décisions validées avec l'utilisateur (session de brainstorming 2026-09-01)

| Décision | Choix retenu |
|---|---|
| Stratégie de déploiement | Big bang complet : le backend exige un jeton valide partout dès son déploiement, pas de transition progressive ni de version d'API parallèle. |
| Garde-fou "mise à jour obligatoire" côté apps | Aucun — accepté explicitement comme risque opérationnel géré hors code (coordination humaine du jour de bascule). |
| Périmètre "toute l'API v1.0" | Seulement les endpoints qui portent une identité (`id_user`/`id_agent`) ou déclenchent une action. Les endpoints purement publics (catalogue, boutiques, pays...) restent sans jeton. |
| Transport du jeton | Champ `token` dans le corps/paramètres de la requête (comme `boutiqueVerifiee()` aujourd'hui), pas d'en-tête `Authorization`. |
| Traitement de `id_user`/`id_agent` envoyé par le client | Ignoré côté serveur une fois authentifié — l'identité de l'appelant vient uniquement du jeton (`tokenable_id`), jamais du paramètre. |
| Vérification de propriété sur les ressources | Oui — un agent authentifié ne doit pas pouvoir agir sur la commande/course d'un autre agent ; `admin`/`employee_afc` gardent leur vue globale légitime (règles 15/16 CLAUDE.md). |

## 5. Architecture

Deux couches, appliquées ensemble sur chaque endpoint concerné :

1. **Authentification** — jeton Sanctum valide → utilisateur réel
   (`tokenable_id`), sinon `401`.
2. **Autorisation** — l'utilisateur authentifié doit être propriétaire/assigné
   de la ressource ciblée, sinon `403`. Exemption : `admin` et `employee_afc`
   (vue globale déjà légitime ailleurs dans l'écosystème, règles 15/16).

## 6. Composants backend

### 6.1 Helper d'authentification partagé

Nouvelle classe `App\Support\ApiAuthentification`, qui remplace la logique
dupliquée de `MaBoutiqueController::boutiqueVerifiee()` :

```php
namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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
}
```

Un trait `App\Http\Controllers\Concerns\ExigeAuthentificationApi` (ou une méthode
protégée sur un contrôleur de base API) ajoute :

```php
protected function utilisateurAuthentifie(Request $request): User|JsonResponse
{
    $utilisateur = app(ApiAuthentification::class)->utilisateur($request);

    if (! $utilisateur) {
        return response()->json([
            'response' => 401,
            'message' => 'Session expirée, reconnectez-vous',
        ]);
    }

    return $utilisateur;
}
```

Chaque méthode de contrôleur protégée commence par vérifier
`instanceof JsonResponse` sur le retour et le renvoyer tel quel si c'est le cas
(même pattern que le early-return déjà utilisé partout dans ce codebase pour les
validations).

`MaBoutiqueController::boutiqueVerifiee()` est réécrite pour utiliser ce nouveau
helper en interne (`Shop::where('id_user', $utilisateur->id)->first()`) plutôt
que de dupliquer `PersonalAccessToken::findToken()` — un seul endroit qui sait
lire un jeton.

### 6.2 Émission du jeton à la connexion

`loginDelivery` et `loginEmployee` gagnent le même appel que `login()` a déjà :

```php
$token = $seachUser->createToken('agent-mobile')->plainTextToken;   // loginDelivery
$token = $seachUser->createToken('employee-mobile')->plainTextToken; // loginEmployee
```

ajouté au tableau `data` de la réponse JSON existante (`token` à côté de
`response`/`message`/`data`). Noms de jeton distincts par app : utile pour
l'audit (`personal_access_tokens.name`) et pour une révocation ciblée future
sans toucher les autres jetons du même compte (cas `role=admin`, qui se connecte
sur les 3 apps mobiles — règle 17 CLAUDE.md, chaque app génère son propre jeton
nommé, un seul compte peut en avoir 3 simultanément).

### 6.3 Révocation

- Déconnexion explicite (si un endpoint de logout existe ou est ajouté) :
  `$utilisateur->currentAccessToken()->delete()`.
- `changePassword` et `changePasswordByOtp` (après succès) : révoquent **tous**
  les jetons du compte (`$user->tokens()->delete()`) — un jeton volé avant la
  réinitialisation ne doit pas rester valide après.
- `deleteUser` : révoque tous les jetons du compte supprimé.

### 6.4 Endpoints à protéger — méthode d'inventaire pour le plan

La liste exhaustive des routes à protéger sera dressée pendant la phase de
planification (`writing-plans`), par un grep systématique de `id_user`,
`id_agent`, `id_client` sur `routes/api.php` et les contrôleurs sous
`app/Http/Controllers/API/`. Catégories déjà identifiées par l'audit du
2026-09-01, à traiter en priorité :

- Position et disponibilité : `updateAgentPosition`, `updatePositionAgent`,
  `updatePositionAgentOrder`, `updateDeliveryPosition`, `takeDay`,
  `takeDayDesactive`.
- Prise/fin de course ou commande : `takeClandoCommand`, `takeOrderCommand`,
  `terminatedCourse`, `terminatedCourseOrder`, `declinCommandAfterTake`,
  `mapAftertake`.
- Finance : `requestWithdrawal`, `getfinanceAgent`, `getPaymentsAgent`.
- Profil/compte : `updateUser`, `deleteUser`, `getInfoUser`.
- Kiosk : `deverrouillerEcranKiosk` (le `token` déjà présent dans le payload
  est le jeton kiosk existant, distinct du jeton Sanctum de session — les deux
  coexistent : le jeton kiosk reste la preuve qu'un QR a été scanné, le jeton
  Sanctum authentifie qui l'a scanné).
- Panier : `deleteCart`, `deleteProductCart`, `updateItem` (déjà signalés IDOR
  moyen dans l'audit).
- Upload : `storeProduct`, `addShop` (déjà corrigés pour la validation de
  fichier ce matin — restent aussi à authentifier).

### 6.5 Vérification de propriété (autorisation)

Pour chaque action ciblant une ressource existante, vérifier avant d'agir que
`$utilisateur->id` correspond au propriétaire/assigné, ou que
`$utilisateur->role` est `admin`/`employee_afc` :

| Ressource | Colonne de propriété | Exemple d'endpoint |
|---|---|---|
| `clando_courses` (à confirmer nom exact table) | `id_agent` assigné | `terminatedCourse`, `declinCommandAfterTake` |
| `orders`/`order_detail` | `id_agent` assigné | `terminatedCourseOrder` |
| `users` (profil) | `id` lui-même | `updateUser`, `deleteUser` |
| `carts` | `id_user` propriétaire | `deleteCart`, `updateItem` |
| `withdrawal_requests` | `id_agent` demandeur | (lecture seule après création — création déjà liée à l'utilisateur authentifié) |

Le détail exact des colonnes sera vérifié modèle par modèle pendant le plan
(certains noms ci-dessus sont approximatifs, à confirmer sur le schéma réel).

## 7. Composants côté 3 apps Flutter

- Stocker le jeton reçu à la connexion via `flutter_secure_storage` (remplace
  `SharedPreferences`/`SessionManager` en clair pour cette donnée précise —
  corrige au passage le finding #5 de l'audit mobile 2026-09-01).
- Ajouter `token` au corps/paramètres de chaque appel vers un endpoint
  désormais protégé (liste dérivée de §6.4).
- Ne pas retirer `id_user`/`id_agent` des appels existants — ignoré côté
  serveur, aucun risque à le laisser, évite de toucher inutilement chaque
  écran.
- Un `401`/`403` reçu sur un appel authentifié doit ramener l'utilisateur à
  l'écran de connexion (jeton absent/révoqué) plutôt qu'afficher une erreur
  générique.
- Chaque app a son propre écran de connexion déjà identifié :
  `plouletafcapp/lib/screens/login_screen/login_screen.dart`,
  `pouletafc_agent/lib/screens/login_screen/login_screen.dart`,
  `empolyeeafc/lib/screens/login_screen/login_screen.dart` — c'est là que le
  jeton reçu doit être capturé et stocké.

## 8. Gestion des erreurs

Même enveloppe JSON que l'existant partout dans cette API :
`{"response": 401, "message": "..."}` (jeton absent/invalide) et
`{"response": 403, "message": "..."}` (authentifié mais pas propriétaire de la
ressource ciblée). Pas de nouveau format à introduire côté client.

## 9. Risque opérationnel connu (accepté)

Le vérificateur de mise à jour actuel (`app_update_service.dart`, présent dans
`plouletafcapp`/`pouletafc_agent`, absent d'`empolyeeafc`) est **non bloquant**
("Plus tard" dismissible), documenté comme tel pour qu'un agent puisse prendre
son service même hors connexion. Avec un big bang backend, tout utilisateur
n'ayant pas mis à jour son app au moment exact de la bascule sera bloqué
jusqu'à mise à jour manuelle — y compris un agent en pleine tournée. Décision
utilisateur explicite (§4) : ce risque est géré opérationnellement (timing de
bascule, communication aux agents), pas par un garde-fou technique dans ce
projet.

## 10. Tests

Pour chaque contrôleur touché, au minimum :
- Requête sans `token` → `401`.
- `token` valide d'un compte différent du propriétaire de la ressource ciblée
  → `403`.
- `token` valide du propriétaire → comportement actuel inchangé (réponse
  identique à avant ce projet).
- Régression sur les contrôles de rôle déjà existants (`loginDelivery`,
  `loginEmployee`, `changeRole`) — ne doivent pas être affaiblis par l'ajout de
  la couche jeton.
- `changePassword`/`changePasswordByOtp` : vérifier qu'un jeton émis avant le
  changement de mot de passe est bien révoqué après.

## 11. Séquencement

Un seul déploiement backend qui active l'exigence de jeton sur les endpoints du
§6.4, synchronisé avec la publication des 3 apps mises à jour. Ordre logique
d'implémentation (détaillé dans le plan) :
1. Backend : helper d'authentification + émission de jeton sur les 3 endpoints
   de connexion + révocation.
2. Backend : protection endpoint par endpoint (auth + propriété), avec tests à
   chaque étape.
3. 3 apps Flutter : stockage sécurisé du jeton + envoi sur les appels
   protégés.
4. Vérification manuelle croisée (backend + chaque app) avant bascule.
5. Mise à jour de `CLAUDE.md` (règle 8) et `ARCHITECTURE.md` pour documenter le
   nouvel état réel — dernière étape, une fois le code effectivement dans cet
   état.

## 12. Risques et limites connues

- Le jeton kiosk existant (`deverrouillerEcranKiosk`) et le jeton Sanctum de
  session sont deux mécanismes différents qui coexistent sur ce même endpoint
  — à ne pas confondre pendant l'implémentation.
- Les noms de colonnes de propriété au §6.5 sont approximatifs, à vérifier sur
  le schéma réel pendant le plan.
- Ce projet ne couvre pas les failles élevées/moyennes restantes de l'audit
  (voir §3, non-objectifs) — à traiter séparément si l'utilisateur le demande.
