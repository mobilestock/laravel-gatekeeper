<?php

namespace MobileStock\Gatekeeper\Events;

use MobileStock\Gatekeeper\Socialite\User;

class UserAuthenticated
{
    public function __construct(public User $user, public array $state)
    {
    }
}
