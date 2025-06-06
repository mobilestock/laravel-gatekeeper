<?php

namespace MobileStock\Gatekeeper\Events;


class UserAuthenticated
{
    public function __construct(public array $state)
    {
    }
}
