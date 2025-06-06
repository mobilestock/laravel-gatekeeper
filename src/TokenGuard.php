<?php

namespace MobileStock\Gatekeeper;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class TokenGuard extends \Illuminate\Auth\TokenGuard
{
    public function __construct(Request $request, $inputKey = 'id', $storageKey = 'id', $hash = false)
    {
        $this->hash = $hash;
        $this->request = $request;
        $this->inputKey = $inputKey;
        $this->storageKey = $storageKey;
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $accessToken = $this->request->bearerToken();

        try {
            $sociliteUser = Socialite::driver('users')->userFromToken($accessToken);
            $user = Socialite::driver('users')->adaptSociliteUserIntoAuthenticatable($sociliteUser);
        } catch (\Throwable) {
        }

        return $this->user = $user;
    }
}
