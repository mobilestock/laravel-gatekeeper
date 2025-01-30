<?php

namespace MobileStock\Gatekeeper;

use Illuminate\Contracts\Auth\UserProvider as AuthUserProvider;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class TokenGuard extends \Illuminate\Auth\TokenGuard
{
    public function __construct(
        ?AuthUserProvider $provider,
        Request $request,
        $inputKey = 'id',
        $storageKey = 'id',
        $hash = false
    ) {
        $this->hash = $hash;
        $this->request = $request;
        $this->provider = $provider;
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
            $user = Socialite::driver('users')->userFromToken($accessToken);
        } catch (\Throwable) {
        }

        return $this->user = $user;
    }
}
