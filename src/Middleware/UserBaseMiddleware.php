<?php

namespace MobileStock\Gatekeeper\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Request;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Enum\Scope;

class UserBaseMiddleware
{
    protected function getUserFromRequest(): object
    {
        $user = $this->resolveUserFromRequest();
        if (in_array(Scope::EXTERNAL_API->value, $user->scopes ?? [], true)) {
            throw new AuthenticationException();
        }

        return $user;
    }

    protected function resolveUserFromRequest(): object
    {
        $accessToken = Request::bearerToken();
        if (empty($accessToken)) {
            throw new AuthenticationException();
        }

        $driver = Socialite::driver('users');
        $user = $driver->userFromToken($accessToken);
        if (empty($user)) {
            throw new AuthenticationException();
        }

        return $user;
    }

    protected function ensureTokenHasAnyScope(array $requiredScopes, array $userScopes): void
    {
        if (in_array('*', $userScopes)) {
            return;
        }

        foreach ($requiredScopes as $scope) {
            if (in_array($scope, $userScopes, true)) {
                return;
            }
        }

        throw new AuthenticationException('Missing required scope');
    }
}
