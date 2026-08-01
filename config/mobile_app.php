<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application mobile CLANDO
    |--------------------------------------------------------------------------
    | Configuration de la distribution de l'app mobile. Tant que la fiche
    | Google Play n'est pas validée, l'APK est proposé en téléchargement direct
    | depuis le site.
    */

    'name' => env('MOBILE_APP_NAME', 'CLANDO'),

    'android' => [

        /*
        | Chemin absolu du fichier APK sur le serveur. Il vit sous storage/app,
        | qui est resynchronisé depuis la release précédente à chaque
        | déploiement (cf. .github/workflows/deploy.yml) : le binaire survit
        | donc aux mises en production sans être versionné dans git.
        */
        'apk_path' => env('MOBILE_APP_APK_PATH', storage_path('app/mobile/clando.apk')),

        /*
        | Nom du fichier proposé au navigateur lors du téléchargement.
        */
        'apk_filename' => env('MOBILE_APP_APK_FILENAME', 'clando.apk'),

        'version' => env('MOBILE_APP_ANDROID_VERSION'),

        'min_os' => env('MOBILE_APP_ANDROID_MIN_OS', 'Android 7.0'),

        /*
        | Renseigner dès que la fiche Play Store est validée : le bouton
        | "Google Play" devient alors actif et le téléchargement direct de
        | l'APK passe en solution secondaire.
        */
        'play_store_url' => env('MOBILE_APP_PLAY_STORE_URL'),
    ],

    'ios' => [
        'app_store_url' => env('MOBILE_APP_APP_STORE_URL'),
    ],

];
