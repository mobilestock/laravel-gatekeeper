<?php

namespace MobileStock\Gatekeeper\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\TokenRepository;
use Laravel\Socialite\Facades\Socialite;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\AuthenticationException as IlluminateAuthenticationException;

class CheckScopesOrAuthorize
{
    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * Token Repository.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $repository;

    /**
     * Create a new middleware instance.
     *
     * @param  \League\OAuth2\Server\ResourceServer  $server
     * @param  \Laravel\Passport\TokenRepository  $repository
     * @return void
     */
    public function __construct(ResourceServer $server, TokenRepository $repository)
    {
        $this->server = $server;
        $this->repository = $repository;
    }

    /**
     * Summary of handle
     * @param Request $request
     * @param Closure $next
     * @param array{scopes:string,guards:string,abilities:string} $rawMiddlewareParameters
     * @throws AuthenticationException
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$rawMiddlewareParameters): Response
    {
        $accessToken = $request->bearerToken();
        if (!$accessToken) {
            throw new AuthenticationException();
        }

        $rawMiddlewareParameters['scopes'] ??= implode('|', []);
        $rawMiddlewareParameters['guards'] ??= implode('|', [null]);
        $rawMiddlewareParameters['abilities'] ??= implode('|', []);
        $configs = array_map(fn(string $key): array => explode('|', $rawMiddlewareParameters[$key]), [
            'scopes',
            'guards',
            'abilities',
        ]);

        $psrFactory = new PsrHttpFactory();
        $psr = $psrFactory->createRequest($request);
        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException();
        }

        $clientId = $psr->getAttribute('oauth_client_id');
        $client = Client::find($clientId);
        if (empty($client->redirect) && !empty($client->user_id)) {
            $driver = Socialite::driver('users');
            if (empty($driver)) {
                $apiUrl = Config::get('services.users.api_url');
                $users = Http::baseUrl($apiUrl)
                    ->get('/api/user', ['ids' => [$client->user_id]])
                    ->throw()
                    ->json();

                $user = current($users);
                $user = new GenericUser((array) $user);
            } else {
                $socialiteUser = $driver->userFromToken($accessToken);
                $user = $driver->adaptSocialiteUserIntoAuthenticatable($socialiteUser);
            }

            Auth::setUser($user);
        }

        return $next($request);
    }
}
