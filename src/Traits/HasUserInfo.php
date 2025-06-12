<?php

namespace MobileStock\Gatekeeper\Traits;

trait HasUserInfo
{
    public function userInfo(): array
    {
        return $this->userInfo;
    }
}
