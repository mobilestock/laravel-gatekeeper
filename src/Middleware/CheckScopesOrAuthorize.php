<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class CheckScopesOrAuthorize
{
    /**
     * Summary of handle
     * @param Request $request
     * @param Closure $next
     * @param array<string> $rawMiddlewareParameters
     * @throws AuthenticationException
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$rawMiddlewareParameters): Response
    {
        $accessToken = $request->bearerToken();
        if (empty($accessToken)) {
            throw new AuthenticationException();
        }

        $configs = ['scopes' => ['*'], 'guards' => [null], 'abilities' => []];
        foreach ($rawMiddlewareParameters as $parameter) {
            [$key, $values] = explode('=', $parameter);
            $configs[$key] = explode('|', $values);
        }
        $configs = Arr::only($configs, ['scopes', 'guards', 'abilities']);

        $driver = Socialite::driver('users');
        $user = $driver->userFromToken($accessToken);

        if ($user->is_client) {
            $this->ensureTokenHasRequiredScopes($configs['scopes'], $user->scopes);
        } else {
            $this->ensureTokenHasRequiredGuard($configs['guards']);
            $this->ensureTokenHasRequiredAbility($configs['abilities']);
        }

        $user = new GenericUser((array) $user);
        Auth::setUser($user);

        return $next($request);
    }

    protected function ensureTokenHasRequiredScopes(array $requiredScopes, array $userScopes): void
    {
        if (in_array('*', $userScopes)) {
            return;
        }

        foreach ($requiredScopes as $scope) {
            if (in_array($scope, $userScopes, true)) {
                return;
            }
        }

        throw new AuthenticationException();
    }

    protected function ensureTokenHasRequiredGuard(array $requiredGuards): void
    {
        foreach ($requiredGuards as $guard) {
            $guardInstance = Auth::guard($guard);
            if ($guardInstance->check()) {
                Auth::shouldUse($guard);
                return;
            }
        }

        throw new AuthenticationException();
    }

    protected function ensureTokenHasRequiredAbility(array $requiredAbilities): void
    {
        if (Gate::allows($requiredAbilities)) {
            return;
        }

        throw new AuthenticationException();
    }
}
