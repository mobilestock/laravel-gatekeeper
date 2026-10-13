<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class AcceptScope extends UserBaseMiddleware
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $appClientId = Config::get('services.users.m2m.client_id');
        if (empty($appClientId)) {
            throw new AuthenticationException();
        }

        try {
            $socialiteUser = $this->resolveUserFromRequest();
        } catch (\Throwable) {
            throw new AuthenticationException();
        }

        if (!in_array($scope, $socialiteUser->scopes ?? [], true)) {
            throw new AuthenticationException('Missing required scope');
        }

        if ($socialiteUser->issuer_client_id !== $appClientId) {
            throw new AuthenticationException();
        }

        $user = Socialite::driver('users')->adaptSocialiteUserIntoAuthenticatable($socialiteUser);
        Auth::setUser($user);

        return $next($request);
    }
}
