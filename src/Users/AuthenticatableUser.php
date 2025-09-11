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

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        $user = $this->userInfo();

        return $user['id'];
    }
}
