<?php

namespace MobileStock\Gatekeeper\Users;

use Illuminate\Auth\GenericUser;
use MobileStock\Gatekeeper\Contracts\User;

class AuthenticatableUser extends GenericUser implements User
{
    public function userInfo(): array
    {
        return $this->attributes;
    }
}
