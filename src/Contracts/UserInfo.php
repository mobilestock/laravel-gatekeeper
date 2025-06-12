<?php

use Illuminate\Contracts\Auth\Authenticatable;

interface UserInfo extends Authenticatable
{
    public function userInfo(): array;
}
