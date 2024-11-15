<?php

namespace MobileStock\Gatekeeper\Socialite;

use Laravel\Socialite\Two\User as TwoUser;

/**
 * @property int $id
 */
class User extends TwoUser
{
    public string $phone_number;
}
