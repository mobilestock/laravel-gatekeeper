<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;

describe('M2M Macro', function () {
    beforeEach(function () {
        Cache::flush();

        Config::set('app.name', 'test-app');
        Config::set('app.env', 'testing');
        Config::set('gatekeeper.users_api_url', 'https://users-api.test');
        Config::set('services.users.m2m.client_id', 'test-client-id');
        Config::set('services.users.m2m.client_secret', 'test-client-secret');
    });

    describe('withM2M macro on PendingRequest', function () {
        it('macro is registered on PendingRequest', function () {
            expect(PendingRequest::hasMacro('withM2M'))->toBeTrue();
        });

        it('applies m2m token to request authorization header', function () {
            $tokenResponse = Mockery::mock();
            $tokenResponse->shouldReceive('json')->with('access_token')->andReturn('bearer-token-xyz');

            $apiResponse = Mockery::mock();
            $apiResponse->shouldReceive('successful')->andReturn(true);

            Http::shouldReceive('baseUrl')->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->andReturn($tokenResponse);

            Http::shouldReceive('baseUrl')->with('https://api.example.com')->andReturnSelf();
            Http::shouldReceive('withM2M')->once()->andReturnSelf();
            Http::shouldReceive('get')->with('/protected-endpoint')->andReturn($apiResponse);

            $response = Http::baseUrl('https://api.example.com')->withM2m()->get('/protected-endpoint');

            expect($response->successful())->toBeTrue();
        });

        it('reuses cached token for multiple requests', function () {
            $tokenResponse = Mockery::mock();
            $tokenResponse->shouldReceive('json')->with('access_token')->andReturn('cached-bearer-token');

            $apiResponse = Mockery::mock();

            Http::shouldReceive('baseUrl')->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->andReturn($tokenResponse);

            Http::shouldReceive('baseUrl')->with('https://api.example.com')->andReturnSelf();
            Http::shouldReceive('withM2M')->twice()->andReturnSelf();
            Http::shouldReceive('get')->andReturn($apiResponse);

            Http::baseUrl('https://api.example.com')->withM2M()->get('/endpoint1');
            Http::baseUrl('https://api.example.com')->withM2M()->get('/endpoint2');

            expect(true)->toBeTrue();
        });
    });
});
