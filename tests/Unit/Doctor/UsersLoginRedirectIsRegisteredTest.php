<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\UsersLoginRedirectIsRegistered;

beforeEach(function () {
    Config::set('gatekeeper.users_api_url', 'https://users-api.test');
    Config::set('services.users.client_id', 'login-client');
    Config::set('services.users.client_secret', 'login-secret');
    Config::set('services.users.redirect', 'https://app.test/oauth/callback');
    Http::preventStrayRequests();
    $this->diagnostic = new UsersLoginRedirectIsRegistered();
    $this->clientAccepted = Http::response(['error' => 'invalid_request'], 400);
    $this->clientRejected = Http::response(['error' => 'invalid_client'], 401);
});

describe('check', function () {
    it('should pass when the client is accepted with the configured redirect URI', function () {
        Http::fake(['https://users-api.test/oauth/token' => $this->clientAccepted]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
        Http::assertSentCount(2);
        Http::assertSent(fn(Request $request) => !array_key_exists('redirect_uri', $request->data()));
        Http::assertSent(fn(Request $request) => ($request->data()['redirect_uri'] ?? null) === 'https://app.test/oauth/callback');
    });

    it('should fail and name USERS_REDIRECT_URI when only the redirect makes users-api reject the client', function () {
        Http::fakeSequence('https://users-api.test/oauth/token')
            ->pushResponse($this->clientAccepted)
            ->pushResponse($this->clientRejected);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('users-login-redirect-is-registered.rejected')
            ->and($result->details)->toBe('A users-api não tem https://app.test/oauth/callback registrado para o cliente.')
            ->and($result->remediation)->toContain('USERS_REDIRECT_URI')
            ->and($result->details . $result->remediation)->not->toContain('login-secret');
    });

    it('should skip after a single request when users-api rejects the client itself', function () {
        Http::fake(['https://users-api.test/oauth/token' => $this->clientRejected]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Skip)
            ->and($result->details)->toContain('recusou o cliente');
        Http::assertSentCount(1);
    });

    it('should skip without a request when the client is not configured', function () {
        Config::set('services.users.client_secret', '');
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Skip);
        Http::assertNothingSent();
    });

    it('should fail without a request when USERS_REDIRECT_URI is empty', function () {
        Config::set('services.users.redirect', null);
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Configure USERS_REDIRECT_URI.');
        Http::assertNothingSent();
    });
});
