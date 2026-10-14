<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Enum\Scope;
use MobileStock\Gatekeeper\Middleware\AcceptScope;
use MobileStock\Gatekeeper\Middleware\CheckScopes;
use MobileStock\Gatekeeper\Users\AuthenticatableUser;

const ACCEPT_SCOPE_APP_CLIENT_ID = '9fa3ed00-420d-4591-bdbb-503ae461d00f';
const ACCEPT_SCOPE_OTHER_APP_CLIENT_ID = '9fa3ed00-6c1e-4a52-9b1f-2d0c8e7a4b13';
const ACCEPT_SCOPE_USER_ID = 30014;
const ACCEPT_SCOPE_TOKEN_ID = 'b4504e272673172b73f24e145c10af58162b88c30241738767a311a2dc084cbc92a3ed44b867a442';

beforeEach(function () {
    Config::set('services.users.m2m.client_id', ACCEPT_SCOPE_APP_CLIENT_ID);
    Config::set('auth.defaults.guard', 'user');
    Config::set('auth.guards.user', ['driver' => 'token_users']);

    Route::middleware(AcceptScope::class . ':' . Scope::EXTERNAL_API->value)->get(
        '/external',
        fn() => [
            'id' => Auth::id(),
            'token_id' => Auth::user()->userInfo()['token_id'],
            'user_class' => get_class(Auth::user()),
        ]
    );
    Route::middleware('auth:user')->get('/internal', fn() => ['id' => Auth::id()]);
    Route::middleware(CheckScopes::class)->get('/internal_m2m', fn() => ['status' => 'ok']);

    $this->usersApiRequests = [];
    $this->usersApiResponses = new MockHandler();
    $handlerStack = HandlerStack::create($this->usersApiResponses);
    $handlerStack->push(Middleware::history($this->usersApiRequests));
    Socialite::driver('users')->setHttpClient(new Client(['handler' => $handlerStack]));

    $this->personalAccessToken = [
        'id' => ACCEPT_SCOPE_USER_ID,
        'name' => 'Loja Integradora',
        'is_client' => false,
        'scopes' => [Scope::EXTERNAL_API->value],
        'token_id' => ACCEPT_SCOPE_TOKEN_ID,
        'issuer_client_id' => ACCEPT_SCOPE_APP_CLIENT_ID,
    ];
});

describe('accept', function () {
    it('should accept a personal access token issued by the app', function () {
        $this->usersApiResponses->append(new GuzzleResponse(200, [], json_encode($this->personalAccessToken)));

        $response = $this->withToken('personal-access-token')->getJson('/external');

        $response->assertOk()->assertExactJson([
            'id' => ACCEPT_SCOPE_USER_ID,
            'token_id' => ACCEPT_SCOPE_TOKEN_ID,
            'user_class' => AuthenticatableUser::class,
        ]);
        expect($this->usersApiRequests)->toHaveCount(1);
    });

    it('should reject a personal access token issued by :dataset', function (?string $issuerClientId) {
        $this->personalAccessToken['issuer_client_id'] = $issuerClientId;
        $this->usersApiResponses->append(new GuzzleResponse(200, [], json_encode($this->personalAccessToken)));

        $response = $this->withToken('personal-access-token')->getJson('/external');

        $response->assertUnauthorized();
    })->with([
        'another app' => [ACCEPT_SCOPE_OTHER_APP_CLIENT_ID],
        'no app' => [null],
    ]);

    it('should reject a token without the literal scope (:dataset)', function (bool $isClient, array $scopes) {
        $this->personalAccessToken['is_client'] = $isClient;
        $this->personalAccessToken['scopes'] = $scopes;
        $this->usersApiResponses->append(new GuzzleResponse(200, [], json_encode($this->personalAccessToken)));

        $response = $this->withToken('internal-token')->getJson('/external');

        $response->assertUnauthorized()->assertJson(['message' => 'Missing required scope']);
    })->with([
        'internal client' => [true, ['*']],
        'site login' => [false, ['access:verified']],
    ]);

    it('should reject a key that the users api does not recognize (:dataset)', function (int $status) {
        Exceptions::fake();
        $this->usersApiResponses->append(
            new GuzzleResponse($status, [], json_encode(['message' => 'Unauthenticated.']))
        );

        $response = $this->withToken('revoked-personal-access-token')->getJson('/external');

        $response->assertUnauthorized();
        Exceptions::assertNothingReported();
    })->with([
        'unauthorized' => [401],
        'forbidden' => [403],
    ]);

    it('should reject a request without a token', function () {
        $response = $this->getJson('/external');

        $response->assertUnauthorized();
        expect($this->usersApiRequests)->toBeEmpty();
    });

    it('should answer service unavailable when the users api fails (:dataset)', function (
        GuzzleResponse|ConnectException $failure
    ) {
        Exceptions::fake();
        $this->usersApiResponses->append($failure);

        $response = $this->withToken('personal-access-token')->getJson('/external');

        $response->assertServiceUnavailable()->assertExactJson(['message' => 'Service Unavailable.']);
        expect(Exceptions::reported())
            ->toHaveCount(1)
            ->each->toBeInstanceOf(GuzzleException::class);
    })->with([
        'server error' => [new GuzzleResponse(500)],
        'service unavailable' => [new GuzzleResponse(503)],
        'unexpected client error' => [new GuzzleResponse(404)],
        'connection failure' => [new ConnectException('Connection timed out', new GuzzleRequest('GET', '/api/me'))],
    ]);

    it('should reject every token when the app has no client id (:dataset)', function (?string $appClientId) {
        Config::set('services.users.m2m.client_id', $appClientId);

        $response = $this->withToken('personal-access-token')->getJson('/external');

        $response->assertUnauthorized();
        expect($this->usersApiRequests)->toBeEmpty();
    })->with([
        'null' => [null],
        'empty string' => [''],
    ]);
});

describe('default refusal', function () {
    it('should reject the external scope on :dataset', function (string $uri, bool $isClient) {
        $this->personalAccessToken['is_client'] = $isClient;
        $this->usersApiResponses->append(new GuzzleResponse(200, [], json_encode($this->personalAccessToken)));

        $response = $this->withToken('personal-access-token')->getJson($uri);

        $response->assertUnauthorized();
    })->with([
        'a guarded route' => ['/internal', false],
        'a client scope route' => ['/internal_m2m', true],
    ]);

    it('should keep authenticating internal tokens on :dataset', function (string $uri, bool $isClient, array $scopes) {
        $this->personalAccessToken['is_client'] = $isClient;
        $this->personalAccessToken['scopes'] = $scopes;
        $this->usersApiResponses->append(new GuzzleResponse(200, [], json_encode($this->personalAccessToken)));

        $response = $this->withToken('internal-token')->getJson($uri);

        $response->assertOk();
    })->with([
        'a guarded route with the site login' => ['/internal', false, ['access:verified']],
        'a guarded route with total access' => ['/internal', true, ['*']],
        'a client scope route with total access' => ['/internal_m2m', true, ['*']],
    ]);
});
