<?php

namespace MobileStock\Gatekeeper;

use Illuminate\Contracts\Auth\UserProvider as AuthUserProvider;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class TokenGuard extends \Illuminate\Auth\TokenGuard
{
    public function __construct(
        protected ?AuthUserProvider $provider,
        protected Request $request,
        protected $inputKey = 'id',
        protected $storageKey = 'id',
        protected $hash = false
    ) {
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $accessToken = $this->request->header('access-token');

        try {
            $user = Socialite::driver('users')->userFromToken($accessToken);
        } catch (\Throwable) {
        }

        if (!empty($user) && !empty($this->provider)) {
            $user = $this->provider->retrieveByCredentials([
                $this->storageKey => $user->id,
            ]);
        }

        return $this->user = $user;
    }
}
