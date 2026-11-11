<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use MobileStock\Gatekeeper\Doctor\ClientVerdict;
use MobileStock\Gatekeeper\Doctor\UsersOAuthProbe;

beforeEach(function () {
    Config::set('gatekeeper.users_api_url', 'https://users-api.test/');
    Http::preventStrayRequests();
});

function usersOAuthResponse(int $status, array $body): Response
{
    $response = new Response(new Psr7Response($status, [], json_encode($body)));

    return $response;
}

describe('tokenUrl', function () {
    it('should append the token path to USERS_APP_URL without a double slash', function () {
        $tokenUrl = UsersOAuthProbe::tokenUrl();

        expect($tokenUrl)->toBe('https://users-api.test/oauth/token');
    });

    it('should be empty when USERS_APP_URL is not configured', function () {
        Config::set('gatekeeper.users_api_url', null);

        $tokenUrl = UsersOAuthProbe::tokenUrl();

        expect($tokenUrl)->toBe('');
    });
});

describe('payloads', function () {
    it('should build an authorization code payload with a deliberately invalid code', function (?string $redirectUri, array $expected) {
        $payload = UsersOAuthProbe::authorizationCodePayload('client-id', 'client-secret', $redirectUri);

        expect($payload)->toBe($expected);
    })->with([
        'without redirect' => [
            null,
            [
                'grant_type' => 'authorization_code',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'code' => UsersOAuthProbe::INVALID_AUTHORIZATION_CODE,
            ],
        ],
        'with redirect' => [
            'https://app.test/oauth/callback',
            [
                'grant_type' => 'authorization_code',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'code' => UsersOAuthProbe::INVALID_AUTHORIZATION_CODE,
                'redirect_uri' => 'https://app.test/oauth/callback',
            ],
        ],
    ]);

    it('should append the deliberately unknown scope after the configured scopes', function (?string $scope, string $expected) {
        $payload = UsersOAuthProbe::clientCredentialsPayload('m2m-id', 'm2m-secret', $scope);

        expect($payload)->toBe([
            'grant_type' => 'client_credentials',
            'client_id' => 'm2m-id',
            'client_secret' => 'm2m-secret',
            'scope' => $expected,
        ]);
    })->with([
        'configured scopes' => ['access:verified login:users', 'access:verified login:users ' . UsersOAuthProbe::INVALID_SCOPE],
        'no scope' => [null, UsersOAuthProbe::INVALID_SCOPE],
    ]);
});

describe('send', function () {
    it('should post the form to the token URL as JSON-accepting request', function () {
        Http::fake(['https://users-api.test/oauth/token' => Http::response(['error' => 'invalid_request'], 400)]);

        $response = UsersOAuthProbe::send(['grant_type' => 'authorization_code']);

        expect($response->status())->toBe(400);
        Http::assertSent(function (Request $request) {
            $isExpected = $request->method() === 'POST'
                && $request->url() === 'https://users-api.test/oauth/token'
                && $request->isForm()
                && $request->hasHeader('Accept', 'application/json')
                && $request->data() === ['grant_type' => 'authorization_code'];

            return $isExpected;
        });
    });

    it('should use the given token URL', function () {
        Http::fake(['https://other.test/oauth/token' => Http::response([], 400)]);

        $response = UsersOAuthProbe::send([], 'https://other.test/oauth/token');

        expect($response->status())->toBe(400);
        Http::assertSentCount(1);
    });

    it('should return the response of a client that throws on errors', function () {
        Http::fake(function () {
            $response = new Response(new Psr7Response(401, [], json_encode(['error' => 'invalid_client'])));

            throw new RequestException($response);
        });

        $response = UsersOAuthProbe::send([]);

        expect($response->status())->toBe(401)
            ->and($response->json('error'))->toBe('invalid_client');
    });

    it('should refuse to send without USERS_APP_URL', function () {
        Config::set('gatekeeper.users_api_url', '');
        Http::fake();

        $send = fn() => UsersOAuthProbe::send([]);

        expect($send)->toThrow(RuntimeException::class, 'Configure USERS_APP_URL.');
        Http::assertNothingSent();
    });
});

describe('authorizationCodeVerdict', function () {
    it('should accept the client when only the code is rejected', function (int $status, string $error) {
        $response = usersOAuthResponse($status, ['error' => $error]);

        $verdict = UsersOAuthProbe::authorizationCodeVerdict($response);

        expect($verdict)->toBe(ClientVerdict::ACCEPTED);
    })->with([
        'invalid_request' => [400, 'invalid_request'],
        'invalid_grant' => [400, 'invalid_grant'],
    ]);

    it('should reject the client on invalid_client', function () {
        $response = usersOAuthResponse(401, ['error' => 'invalid_client']);

        $verdict = UsersOAuthProbe::authorizationCodeVerdict($response);

        expect($verdict)->toBe(ClientVerdict::REJECTED);
    });

    it('should report any other answer with the status and error code only', function (Response $response, string $message) {
        $verdict = fn() => UsersOAuthProbe::authorizationCodeVerdict($response);

        expect($verdict)->toThrow(RuntimeException::class, $message);
    })->with([
        'issued token' => [
            fn() => usersOAuthResponse(200, ['access_token' => 'sensitive-token']),
            'Resposta inesperada da users-api: HTTP 200.',
        ],
        'server error' => [
            fn() => usersOAuthResponse(500, ['error' => 'server_error']),
            'Resposta inesperada da users-api: HTTP 500, erro server_error.',
        ],
        'html page' => [
            fn() => new Response(new Psr7Response(404, [], '<html>sensitive</html>')),
            'Resposta inesperada da users-api: HTTP 404.',
        ],
        'unsafe error text' => [
            fn() => usersOAuthResponse(400, ['error' => 'Bearer sensitive-token']),
            'Resposta inesperada da users-api: HTTP 400.',
        ],
    ]);
});

describe('clientCredentialsVerdict', function () {
    it('should accept the client when only the sentinel scope is unknown', function (array $body) {
        $response = usersOAuthResponse(400, $body);

        $verdict = UsersOAuthProbe::clientCredentialsVerdict($response);

        expect($verdict)->toBe(ClientVerdict::ACCEPTED);
    })->with([
        'hint names the sentinel' => [
            ['error' => 'invalid_scope', 'hint' => 'Check the `' . UsersOAuthProbe::INVALID_SCOPE . '` scope'],
        ],
        'no hint' => [['error' => 'invalid_scope']],
    ]);

    it('should name a configured scope that users-api does not know', function () {
        $response = usersOAuthResponse(400, ['error' => 'invalid_scope', 'hint' => 'Check the `nope:scope` scope']);

        $verdict = fn() => UsersOAuthProbe::clientCredentialsVerdict($response);

        expect($verdict)->toThrow(RuntimeException::class, 'A users-api não conhece o scope nope:scope configurado.');
    });

    it('should reject the client on invalid_client', function () {
        $response = usersOAuthResponse(401, ['error' => 'invalid_client']);

        $verdict = UsersOAuthProbe::clientCredentialsVerdict($response);

        expect($verdict)->toBe(ClientVerdict::REJECTED);
    });

    it('should report any other answer', function (int $status, array $body) {
        $response = usersOAuthResponse($status, $body);

        $verdict = fn() => UsersOAuthProbe::clientCredentialsVerdict($response);

        expect($verdict)->toThrow(RuntimeException::class, "Resposta inesperada da users-api: HTTP $status");
    })->with([
        'issued token' => [200, ['access_token' => 'sensitive-token']],
        'other 400' => [400, ['error' => 'invalid_request']],
    ]);
});
