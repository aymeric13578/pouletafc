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

        /*
        | Numéro de build (versionCode Android, ex. le "38" de "1.0.0+38"
        | dans pubspec.yaml) — à incrémenter à chaque nouvel APK mis en
        | ligne. C'est la valeur que l'app compare à la sienne pour savoir
        | si une mise à jour est disponible (voir AppVersionController) :
        | 0 par défaut désactive le popup plutôt que de le déclencher pour
        | tout le monde si ce réglage est oublié après un déploiement.
        */
        'version_code' => (int) env('MOBILE_APP_ANDROID_VERSION_CODE', 0),

        /*
        | Build le plus ancien encore accepté. En dessous, l'application
        | affiche une mise à jour obligatoire (dialogue non fermable) au lieu
        | du simple popup — c'est le levier qui retire de la circulation un
        | build qui n'envoie pas encore la distance à la création d'une
        | course (CLAUDE.md règle 23 : sans distance, le prix client n'est
        | pas recalculé). 0 = aucune exigence.
        */
        'min_version_code' => (int) env('MOBILE_APP_ANDROID_MIN_VERSION_CODE', 0),

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

        // Voir le même réglage côté android ci-dessus (AppVersionController) —
        // à incrémenter à chaque nouvel APK agent mis en ligne.
        'version_code' => (int) env('MOBILE_APP_AGENT_VERSION_CODE', 0),

        // Même sémantique que android.min_version_code ci-dessus.
        'min_version_code' => (int) env('MOBILE_APP_AGENT_MIN_VERSION_CODE', 0),

        'min_os' => env('MOBILE_APP_AGENT_MIN_OS', 'Android 7.0'),
    ],

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

];
