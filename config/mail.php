<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    /*
    | Transport par défaut.
    |
    | Le .env du serveur porte encore les valeurs d'exemple de Laravel —
    | MAIL_HOST=mailpit sur le port 1025, l'outil de développement, qui n'existe
    | pas en production. Chaque envoi échouait donc en silence : l'inscription
    | attrape l'exception et la journalise, si bien que le compte se créait sans
    | que le message ne parte jamais.
    |
    | Ce .env n'est pas versionné et n'est pas réécrit par le déploiement. On
    | reconnaît donc ici la configuration de développement pour lui préférer le
    | serveur de courrier local de l'hébergement, qui n'exige aucun identifiant.
    | Renseigner un vrai SMTP dans le .env du serveur reprend aussitôt la main.
    */
    'default' => (function () {
        $mailer = env('MAIL_MAILER', 'smtp');
        $host = env('MAIL_HOST');

        if ($mailer === 'smtp' && in_array($host, ['mailpit', 'localhost', '127.0.0.1', null], true)) {
            return 'sendmail';
        }

        return $mailer;
    })(),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => null,
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        /*
         | Même raison que pour le transport : le .env du serveur fixe encore
         | « hello@example.com ». La plupart des serveurs receveurs refusent ou
         | classent en indésirable un message venu de example.com, si bien que
         | même un envoi techniquement réussi n'arrivait pas.
         */
        'address' => (function () {
            $adresse = env('MAIL_FROM_ADDRESS', 'noreply@pouletafc.com');

            return $adresse === 'hello@example.com' ? 'noreply@pouletafc.com' : $adresse;
        })(),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
