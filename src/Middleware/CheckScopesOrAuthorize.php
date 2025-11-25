<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckScopesOrAuthorize extends UserBaseMiddleware
{
    public function handle(Request $request, Closure $next, ...$rawMiddlewareParameters): Response
    {
        $user = $this->getUserFromRequest();

        $configs = ['scopes' => ['*'], 'guards' => [null], 'abilities' => []];
        foreach ($rawMiddlewareParameters as $parameter) {
            [$key, $values] = explode('=', $parameter);
            $configs[$key] = explode('|', $values);
        }
        $configs = Arr::only($configs, ['scopes', 'guards', 'abilities']);

        if ($user->is_client) {
            $this->ensureTokenHasAnyScope($configs['scopes'], $user->scopes);
        } else {
            $this->ensureTokenHasRequiredGuards($configs['guards']);
            $this->ensureTokenHasRequiredAbilities($configs['abilities']);
        }

        $user = new GenericUser((array) $user);
        Auth::setUser($user);

        return $next($request);
    }

    protected function ensureTokenHasRequiredGuards(array $requiredGuards): void
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

    protected function ensureTokenHasRequiredAbilities(array $requiredAbilities): void
    {
        if (empty($requiredAbilities) || Gate::any($requiredAbilities)) {
            return;
        }

        throw new AuthenticationException();
    }
}
