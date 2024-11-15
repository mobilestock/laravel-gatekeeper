<?php

namespace MobileStock\OAuth2Helper\Socialite;

use Laravel\Socialite\Two\User as TwoUser;

/**
 * @property int $id
 */
class User extends TwoUser
{
    public string $phone_number;
}
