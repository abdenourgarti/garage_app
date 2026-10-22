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

];
