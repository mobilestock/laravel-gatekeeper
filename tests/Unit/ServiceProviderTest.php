<?php

use MobileStock\Gatekeeper\Socialite\User;

dataset('admin gate', [
    'user is admin' => [true],
    'user is not admin' => [false],
]);

it('checks if user is admin with gate', function (bool $isAdmin) {
    $user = new User();
    $user->is_admin = $isAdmin;

    $result = Gate::forUser($user)->allows('admin', $user);

    expect($result)->toBe($isAdmin);
})->with('admin gate');
