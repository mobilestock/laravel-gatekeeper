<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

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
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();
            if (in_array($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true)) {
                throw new AuthenticationException();
            }

            report($exception);
            throw new ServiceUnavailableHttpException(previous: $exception);
        } catch (Throwable $exception) {
            report($exception);
            throw new ServiceUnavailableHttpException(previous: $exception);
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
