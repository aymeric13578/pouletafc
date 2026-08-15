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

    /*
    |--------------------------------------------------------------------------
    | Application AGENT
    |--------------------------------------------------------------------------
    | Distribuée séparément : elle ne s'adresse pas au public mais aux livreurs
    | et conducteurs recrutés. Elle ne figure donc sur aucune page publique — on
    | y accède par un lien que l'administration partage depuis l'écran Agents.
    |
    | Même emplacement de stockage que l'application cliente, sous storage/app,
    | resynchronisé depuis la release précédente à chaque déploiement : le
    | binaire survit aux mises en production sans être versionné dans git.
    */
    'agent' => [

        'name' => env('MOBILE_APP_AGENT_NAME', 'Poulet AFC Agent'),

        'apk_path' => env('MOBILE_APP_AGENT_APK_PATH', storage_path('app/mobile/pouletafc-agent.apk')),

        'apk_filename' => env('MOBILE_APP_AGENT_APK_FILENAME', 'pouletafc-agent.apk'),

        'version' => env('MOBILE_APP_AGENT_VERSION'),

        'min_os' => env('MOBILE_APP_AGENT_MIN_OS', 'Android 7.0'),
    ],

];
