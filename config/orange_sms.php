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
    | Adresse d'émission. « tel:+2370000000 » est le remplissage des offres
    | self-service : Orange l'accepte, mais ne remet rien tant qu'aucun nom
    | d'expéditeur n'est enregistré sur le contrat.
    */
    'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS', 'tel:+2370000000'),

    /*
    |--------------------------------------------------------------------------
    | Nom d'expéditeur
    |--------------------------------------------------------------------------
    |
    | Volontairement vide par défaut, et transmis seulement s'il est renseigné.
    |
    | Orange refuse tout nom non enregistré sur le contrat — « Forbidden
    | senderName : not whitelisted » — et l'envoi échoue alors entièrement. Le
    | poser en dur avant leur accord remplacerait des messages acceptés mais non
    | remis par des messages refusés : aucun des deux n'arrive, mais le second
    | casse en plus le parcours d'inscription, qui attend une réponse.
    |
    | Le jour où Orange enregistre « POULETAFC », il suffira d'ajouter au .env
    | du serveur :
    |
    |     ORANGE_SMS_SENDER_NAME=POULETAFC
    |
    | sans toucher au code ni redéployer.
    |
    */
    'sender_name' => env('ORANGE_SMS_SENDER_NAME'),

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
