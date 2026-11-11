<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\UsersApiIsReachable;

beforeEach(function () {
    Config::set('gatekeeper.users_api_url', 'https://users-api.test/');
    Http::preventStrayRequests();
    $this->diagnostic = new UsersApiIsReachable();
});

describe('check', function () {
    it('should pass when /api/me rejects the missing token with 401', function () {
        Http::fake(['https://users-api.test/api/me' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
        Http::assertSent(function (Request $request) {
            $isExpected = $request->method() === 'GET'
                && $request->url() === 'https://users-api.test/api/me'
                && !$request->hasHeader('Authorization')
                && $request->hasHeader('Accept', 'application/json');

            return $isExpected;
        });
    });

    it('should bound the wait and not follow redirects', function () {
        Http::fake(function (Request $request, array $options) {
            $this->requestOptions = $options;
            $response = Http::response(['message' => 'Unauthenticated.'], 401);

            return $response;
        });

        $this->diagnostic->check();

        expect($this->requestOptions)->toMatchArray(['connect_timeout' => 5, 'timeout' => 10, 'allow_redirects' => false]);
    });

    it('should fail and name USERS_APP_URL for any other status', function (int $status) {
        Http::fake(['https://users-api.test/api/me' => Http::response('', $status)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe("GET /api/me sem token deveria responder HTTP 401, respondeu HTTP $status.")
            ->and($result->remediation)->toContain('USERS_APP_URL');
    })->with([200, 302, 404, 500, 503]);

    it('should read the status of a client that throws on errors', function () {
        Http::fake(fn() => throw new RequestException(new Response(new Psr7Response(401))));

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
    });

    it('should fail when users-api does not answer', function () {
        Http::fake(fn() => throw new ConnectionException('timeout'));

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Sem resposta em 10 s ou conexão recusada.')
            ->and($result->remediation)->toContain('USERS_APP_URL');
    });

    it('should fail without a request when USERS_APP_URL is empty', function () {
        Config::set('gatekeeper.users_api_url', null);
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Configure USERS_APP_URL.');
        Http::assertNothingSent();
    });
});
