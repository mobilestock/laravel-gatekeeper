<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CheckForAnyScope extends UserBaseMiddleware
{
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        $user = $this->getUserFromRequest();
        if (!$user->is_client) {
            throw new UnauthorizedHttpException('Only client tokens are allowed');
        }

        $this->ensureTokenHasAnyScope($scopes, $user->scopes);

        return $next($request);
    }
}
