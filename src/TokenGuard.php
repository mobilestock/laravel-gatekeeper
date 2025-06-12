<?php

namespace MobileStock\Gatekeeper;

use Illuminate\Contracts\Auth\UserProvider as AuthUserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    /**
     * @issue https://github.com/mobilestock/backend/issues/1006
     */
    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $accessToken = $this->request->bearerToken();

        try {
            $sociliteUser = Socialite::driver('users')->userFromToken($accessToken);
            $user = Socialite::driver('users')->adaptSocialiteUserIntoAuthenticatable($sociliteUser);
        } catch (\Throwable) {
        }

        if (!empty($sociliteUser) && !empty($this->provider)) {
            $entity = $this->provider->retrieveByCredentials([
                $this->storageKey => $sociliteUser->id,
            ]);

            if (!empty($entity)) {
                $entity->userInfo = Arr::except($sociliteUser->user, 'id');
                $user = $entity;
            }
        }

        return $this->user = $user;
    }
}
