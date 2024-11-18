<?php

use MobileStock\Gatekeeper\Controllers\UserController;

Route::prefix('oauth')->group(function () {
    Route::get('/redirect', [UserController::class, 'redirect']);
    Route::get('/callback', [UserController::class, 'callback']);
});
