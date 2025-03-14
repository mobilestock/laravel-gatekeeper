<?php

use MobileStock\Gatekeeper\Socialite\User;

it('should return user identifier', function () {
    $user = new User();
    $user->id = 1;

    $userIdentifier = $user->getAuthIdentifier();

    expect($userIdentifier)->toBe($user->id);
});
