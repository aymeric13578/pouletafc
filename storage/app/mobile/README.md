# APK de l'application CLANDO

Déposer ici le fichier **`clando.apk`** (le binaire signé de production).

```
storage/app/mobile/clando.apk
```

Il est servi par la route `shop.app.android.apk`
(`https://pouletafc.com/download-app/clando.apk`), via
`App\Http\Controllers\Shop\MobileAppController`.

Le lien public à communiquer est la page **https://pouletafc.com/download-app**
(bouton de téléchargement + marche à suivre pour l'installation hors store).

## Pourquoi ici et pas dans `public/`

* Le binaire n'est **pas versionné** dans git (un APK pèse plusieurs dizaines de Mo,
  et GitHub refuse les fichiers > 100 Mo).
* `storage/app/` est resynchronisé depuis la release précédente à chaque déploiement
  (étape 5 de `.github/workflows/deploy.yml`) : **le fichier survit aux mises en production**,
  alors que tout ce qui est sous `public/` est écrasé par le contenu de l'archive.
* Servir l'APK par PHP force le bon type MIME
  (`application/vnd.android.package-archive`) ; plusieurs hébergements mutualisés
  renvoient un `.apk` statique en `text/plain`, ce qui empêche l'installation.

## Mettre à jour l'application

1. Envoyer le nouvel APK par SFTP dans `storage/app/mobile/clando.apk` (écraser l'ancien).
2. Mettre à jour `MOBILE_APP_ANDROID_VERSION` dans le `.env` du serveur.
3. `php artisan config:cache`.

La taille et la date affichées sur le site sont lues directement sur le fichier :
rien d'autre à modifier.

## Quand la fiche Play Store est validée

Renseigner `MOBILE_APP_PLAY_STORE_URL` dans le `.env` : le bouton « Google Play »
devient automatiquement actif sur la page d'accueil et sur `/application-mobile`.
Le téléchargement direct de l'APK reste disponible en parallèle.
