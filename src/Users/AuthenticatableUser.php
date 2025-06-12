<?php

use Illuminate\Auth\GenericUser;
use MobileStock\Gatekeeper\Contracts\UserDetails;

class AuthenticatableUser extends GenericUser implements UserDetails
{
    public function userInfo(): array
    {
        return $this->attributes;
    }
}
