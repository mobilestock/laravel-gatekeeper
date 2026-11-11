<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\UsersLoginClientIsAccepted;
use MobileStock\Gatekeeper\Doctor\UsersOAuthProbe;

beforeEach(function () {
    Config::set('gatekeeper.users_api_url', 'https://users-api.test');
    Config::set('services.users.client_id', 'login-client');
    Config::set('services.users.client_secret', 'login-secret');
    Http::preventStrayRequests();
    $this->diagnostic = new UsersLoginClientIsAccepted();
});

describe('check', function () {
    it('should pass when users-api rejects only the invalid code', function () {
        Http::fake(['https://users-api.test/oauth/token' => Http::response(['error' => 'invalid_request'], 400)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
        Http::assertSent(function (Request $request) {
            $isExpected = $request->method() === 'POST'
                && $request->url() === 'https://users-api.test/oauth/token'
                && $request->data() === [
                    'grant_type' => 'authorization_code',
                    'client_id' => 'login-client',
                    'client_secret' => 'login-secret',
                    'code' => UsersOAuthProbe::INVALID_AUTHORIZATION_CODE,
                ];

            return $isExpected;
        });
        Http::assertSentCount(1);
    });

    it('should fail and name the client variables when users-api rejects the client', function () {
        Http::fake(['https://users-api.test/oauth/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('users-login-client-is-accepted.rejected')
            ->and($result->details)->toBe('A users-api recusou o cliente (HTTP 401 invalid_client).')
            ->and($result->remediation)->toContain('USERS_CLIENT_ID', 'USERS_CLIENT_SECRET')
            ->and($result->details . $result->remediation)->not->toContain('login-secret');
    });

    it('should fail on an unexpected answer without exposing the response body', function () {
        Http::fake(['https://users-api.test/oauth/token' => Http::response(['access_token' => 'sensitive-token'])]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Resposta inesperada da users-api: HTTP 200.');
    });

    it('should fail when users-api never identified the client', function (string $clientId) {
        Config::set('services.users.client_id', $clientId);
        Http::fake([
            'https://users-api.test/oauth/token' => Http::response(
                ['error' => 'invalid_request', 'hint' => 'Check the `client_id` parameter'],
                400
            ),
        ]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail);
    })->with([
        'blank' => '   ',
        'invisible to PHP trim but trimmed by users-api' => "\u{200B}",
    ]);

    it('should fail when users-api does not answer', function () {
        Http::fake(fn() => throw new ConnectionException('timeout'));

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Sem resposta em 10 s ou conexão recusada.');
    });

    it('should fail without a request when the client is not configured', function (string $key, ?string $value) {
        Config::set("services.users.$key", $value);
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Configure USERS_CLIENT_ID e USERS_CLIENT_SECRET.');
        Http::assertNothingSent();
    })->with([
        'missing client_id' => ['client_id', null],
        'missing client_secret' => ['client_secret', null],
        'blank client_id' => ['client_id', '   '],
        'blank client_secret' => ['client_secret', "\t\n"],
    ]);
});
