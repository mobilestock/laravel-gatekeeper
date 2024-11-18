<?php

return [
    'users' => [
        'client_id' => 'dummy',
        'client_secret' => 'dummy',
        'redirect' => 'http://localhost/auth/callback',
        'frontend' => env('USERS_FRONT_URL'),
        'backend' => env('USERS_APP_URL'),
    ],
];
