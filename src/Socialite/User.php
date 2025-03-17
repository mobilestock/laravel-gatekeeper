<?php

namespace MobileStock\Gatekeeper\Socialite;

use Laravel\Socialite\Two\User as TwoUser;

/**
 * @property int $id
 * @property string $phone_number
 * @property bool $is_admin
 */
class User extends TwoUser
{
}
