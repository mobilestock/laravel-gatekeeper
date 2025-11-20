<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Token;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class CheckScopesOrAuthorize extends CheckClientCredentials
{
    /**
     * Formato esperado dos parâmetros:
     *
     * check_scopes_or_authorize:scopes=access|verified,abilities=APPROVED_SUPPLIER|CHECK_REPORTS
     *
     * - "scopes": lista de escopos do Passport separados por "|"
     * - "abilities": lista de permissions/abilities separados por "|"
     *
     * Se pelo menos UMA das listas validar, a requisição é liberada.
     */
    public function handle($request, Closure $next, ...$rawMiddlewareParameters): Response
    {
        $rules = ['abilities' => [], 'scopes' => ['*']];

        foreach ($rawMiddlewareParameters as $rawParameter) {
            [$parameterKey, $parameterValue] = explode('=', $rawParameter, 2);
            if (empty($parameterKey) || empty($parameterValue)) {
                continue;
            }

            $rules[$parameterKey] = explode('|', $parameterValue);
            $rules[$parameterKey] = array_filter($rules[$parameterKey]);
        }

        $authenticatedUser = Auth::user();
        // $authenticatedUserToken = $authenticatedUser?->token();

        $userValidatedAtLeastOneAbility = $this->userValidatedAnyRequiredAbility(
            $authenticatedUser,
            $rules['abilities']
        );
        if ($userValidatedAtLeastOneAbility) {
            return $next($request);
        }

        $psrFactory = new PsrHttpFactory();
        $psr = $psrFactory->createRequest($request);
        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException();
        }

        $this->validate($psr, $rules['scopes']);

        $accessTokenId = $psr->getAttribute('oauth_access_token_id');
        $accessToken = $this->repository->find($accessTokenId);
        if (empty($accessToken->user_id)) {
            throw new AuthenticationException('No user linked to this system');
        }

        // $user = $this->repository->forUser($accessToken->user_id);
        // Auth::setUser($user);

        return $next($request);
    }

    /**
     * Verifica se o token possui TODOS os escopos necessários.
     *
     * @param  ?Token  $authenticatedUserToken
     * @param  array<int, string>  $requiredScopesList
     */
    protected function tokenValidatedAnyScopes(?Token $authenticatedUserToken, array $requiredScopesList): bool
    {
        if (empty($authenticatedUserToken)) {
            return false;
        }

        foreach ($requiredScopesList as $requiredScopeName) {
            if ($authenticatedUserToken->can($requiredScopeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se o usuário possui pelo menos UMA das abilities requeridas.
     *
     * @param  mixed  $authenticatedUser
     * @param  array<int, string>  $requiredAbilitiesList
     */
    protected function userValidatedAnyRequiredAbility(mixed $authenticatedUser, array $requiredAbilitiesList): bool
    {
        if (empty($authenticatedUser)) {
            return false;
        }

        $hasSomeAbility = Gate::any($requiredAbilitiesList);

        return $hasSomeAbility;
    }
}
