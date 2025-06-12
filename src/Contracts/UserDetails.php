<?php

namespace MobileStock\Gatekeeper\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserDetails extends Authenticatable
{
    public function userInfo(): array;
}
