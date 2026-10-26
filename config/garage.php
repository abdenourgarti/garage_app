<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte gérant initial
    |--------------------------------------------------------------------------
    |
    | Créé par ReferenceDataSeeder lors de l'installation. Le mot de passe
    | doit être changé à la première connexion.
    |
    */

    'admin' => [
        'name' => env('GARAGE_ADMIN_NAME', 'Gérant'),
        'username' => env('GARAGE_ADMIN_USERNAME', 'gerant'),
        'password' => env('GARAGE_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sauvegardes
    |--------------------------------------------------------------------------
    |
    | Binaires MariaDB/MySQL : le nom suffit s'ils sont dans le PATH (Linux),
    | sinon le chemin complet (Windows : C:\GarageApp\mariadb\bin\mariadb-dump.exe).
    | Le dossier de copie (D:, clé USB) se règle dans Paramètres > Sauvegardes.
    |
    */

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        // Fichiers joints sauvegardés et remplacés à la restauration (logo, factures fournisseurs, preuves).
        'files' => storage_path('app/private'),
        'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),
        'client_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Licence
    |--------------------------------------------------------------------------
    |
    | Vérifiée en production ; LICENSE_ENABLED=false la coupe (développement).
    | La clé est un fichier hors base : une restauration ne la perd pas.
    |
    */

    'license' => [
        'enabled' => (bool) env('LICENSE_ENABLED', env('APP_ENV') === 'production'),
        'path' => env('LICENSE_PATH', storage_path('app/license.key')),
    ],

];
