<?php

namespace MobileStock\Gatekeeper\Traits;

trait HasUserInfo
{
    public function userInfo(): array
    {
        return $this->userInfo;
    }

    public function getAuthIdentifier(): int
    {
        $user = $this->userInfo();

        return $user['id'];
    }
}
