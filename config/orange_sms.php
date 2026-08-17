<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Envoi de SMS par l'API Orange
    |--------------------------------------------------------------------------
    |
    | Ces valeurs étaient écrites en dur dans app/Fonction/Fonction.php, donc
    | versionnées et identiques partout. Les défauts reprennent exactement ce
    | qui tournait : sans variable d'environnement, le comportement ne change
    | pas d'un caractère.
    |
    */

    /*
    | Identifiant d'application, en Basic auth. À déplacer dans le .env du
    | serveur dès que possible : tant qu'il est ici, il vit dans l'historique
    | du dépôt.
    */
    'authorization' => env(
        'ORANGE_SMS_AUTHORIZATION',
        'Basic UEJhQXVKMWUzemtzc2JvWTJHTWRUM0hIS2FNUEhCcVY6Yk1rVEZIbVJlWnJvakI1bkFjeGNlZTgxbkpRSndxUFIwU0xVWjVBZDRTWmw='
    ),

    'token_url' => env('ORANGE_SMS_TOKEN_URL', 'https://api.orange.com/oauth/v3/token'),

    /*
    | Adresse d'émission.
    |
    | Orange ne la contrôle pas : le remplissage « tel:+2370000000 », un numéro
    | réel ou un numéro inventé sont acceptés à l'identique — testé, trois fois
    | 201. Elle n'est donc pas un levier sur la remise des messages.
    */
    'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS', 'tel:+2370000000'),

    /*
    | Indicatif préfixé aux numéros locaux à neuf chiffres.
    */
    'country_code' => env('ORANGE_SMS_COUNTRY_CODE', '+237'),

    /*
    | Vérification du certificat. Elle était désactivée ; on la garde ainsi par
    | défaut pour ne rien changer, mais elle devient activable sans toucher au
    | code le jour où l'hébergement le permet.
    */
    'verify_ssl' => (bool) env('ORANGE_SMS_VERIFY_SSL', false),

];
