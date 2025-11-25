<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CheckScopes extends UserBaseMiddleware
{
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        $user = $this->getUserFromRequest();
        if (!$user->is_client) {
            throw new UnauthorizedHttpException('', 'Only client tokens are allowed');
        }

        $this->ensureTokenHasRequiredScopes($scopes, $user->scopes);

        return $next($request);
    }

    protected function ensureTokenHasRequiredScopes(array $requiredScopes, array $userScopes): void
    {
        if (in_array('*', $userScopes)) {
            return;
        }

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $userScopes, true)) {
                throw new AuthenticationException('Missing required scope');
            }
        }
    }
}
