<?php

namespace MobileStock\OAuth2Helper\Events;

use MobileStock\OAuth2Helper\Socialite\User;

class UserAuthenticated
{
    public function __construct(public User $user)
    {
    }
}
