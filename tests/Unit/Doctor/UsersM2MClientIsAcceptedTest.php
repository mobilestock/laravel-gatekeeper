<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\UsersM2MClientIsAccepted;
use MobileStock\Gatekeeper\Doctor\UsersOAuthProbe;

beforeEach(function () {
    Config::set('gatekeeper.users_api_url', 'https://users-api.test');
    Config::set('services.users.m2m', [
        'client_id' => 'm2m-client',
        'client_secret' => 'm2m-secret',
        'scope' => 'access:verified login:users',
    ]);
    Http::preventStrayRequests();
    $this->diagnostic = new UsersM2MClientIsAccepted();
    $this->sentinelRejected = Http::response([
        'error' => 'invalid_scope',
        'hint' => 'Check the `' . UsersOAuthProbe::INVALID_SCOPE . '` scope',
    ], 400);
});

describe('check', function () {
    it('should pass without issuing or caching a token', function () {
        Http::fake(['https://users-api.test/oauth/token' => $this->sentinelRejected]);
        $cache = Cache::spy();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
        Http::assertSent(function (Request $request) {
            $isExpected = $request->url() === 'https://users-api.test/oauth/token'
                && $request->data() === [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'm2m-client',
                    'client_secret' => 'm2m-secret',
                    'scope' => 'access:verified login:users ' . UsersOAuthProbe::INVALID_SCOPE,
                ];

            return $isExpected;
        });
        Http::assertSentCount(1);
        $cache->shouldNotHaveReceived('remember');
        $cache->shouldNotHaveReceived('put');
    });

    it('should fail and name the M2M variables when users-api rejects the client', function () {
        Http::fake(['https://users-api.test/oauth/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('A users-api recusou o cliente M2M (HTTP 401 invalid_client).')
            ->and($result->remediation)->toContain('USERS_M2M_CLIENT_ID', 'USERS_M2M_CLIENT_SECRET')
            ->and($result->details . $result->remediation)->not->toContain('m2m-secret');
    });

    it('should fail and name a configured scope that users-api does not know', function () {
        Http::fake([
            'https://users-api.test/oauth/token' => Http::response(
                ['error' => 'invalid_scope', 'hint' => 'Check the `login:users` scope'],
                400
            ),
        ]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('A users-api não conhece o scope login:users configurado.');
    });

    it('should fail without a request when the M2M credentials are incomplete', function (string $key) {
        Config::set("services.users.m2m.$key", '');
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Configure o endpoint e as credenciais M2M da users-api.');
        Http::assertNothingSent();
    })->with(['client_id', 'client_secret']);

    it('should fail without a request when USERS_APP_URL is empty', function () {
        Config::set('gatekeeper.users_api_url', '');
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail);
        Http::assertNothingSent();
    });

    it('should skip when the app has no M2M client', function () {
        Config::set('services.users', ['client_id' => 'login-client']);
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Skip)
            ->and($result->details)->toBe('O app não usa cliente M2M da users-api.');
        Http::assertNothingSent();
    });
});
