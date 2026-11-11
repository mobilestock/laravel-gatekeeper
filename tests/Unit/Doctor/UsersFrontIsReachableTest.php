<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Doctor\Results\Status;
use MobileStock\Gatekeeper\Doctor\UsersFrontIsReachable;

beforeEach(function () {
    Config::set('gatekeeper.users_front_url', 'https://login.test');
    Http::preventStrayRequests();
    $this->diagnostic = new UsersFrontIsReachable();
});

describe('check', function () {
    it('should pass when the login page answers 200', function () {
        Http::fake(['https://login.test' => Http::response('<html></html>')]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Pass);
        Http::assertSentCount(1);
    });

    it('should fail and name USERS_FRONT_URL for any other status', function (int $status) {
        Http::fake(['https://login.test' => Http::response('', $status)]);

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe("GET USERS_FRONT_URL deveria responder HTTP 200, respondeu HTTP $status.")
            ->and($result->remediation)->toContain('USERS_FRONT_URL');
    })->with([404, 500, 502]);

    it('should read the status of a client that throws on errors', function () {
        Http::fake(fn() => throw new RequestException(new Response(new Psr7Response(404))));

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toContain('HTTP 404');
    });

    it('should fail when the login page does not answer', function () {
        Http::fake(fn() => throw new ConnectionException('timeout'));

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->remediation)->toContain('USERS_FRONT_URL');
    });

    it('should fail without a request when USERS_FRONT_URL is empty', function () {
        Config::set('gatekeeper.users_front_url', null);
        Http::fake();

        $result = $this->diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->details)->toBe('Configure USERS_FRONT_URL.');
        Http::assertNothingSent();
    });
});
