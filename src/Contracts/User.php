<?php

namespace MobileStock\Gatekeeper\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface User extends Authenticatable
{
    public function userInfo(): array;
}
