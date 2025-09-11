<?php

namespace MobileStock\Gatekeeper\Traits;

trait HasUserInfo
{
    public function userInfo(): array
    {
        return $this->userInfo;
    }

    public function getAuthIdentifier(): mixed
    {
        $user = $this->userInfo();

        return $user['id'];
    }
}
