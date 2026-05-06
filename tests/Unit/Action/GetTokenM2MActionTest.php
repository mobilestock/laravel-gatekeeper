<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use MobileStock\Gatekeeper\Action\GetTokenM2MAction;

describe('GetTokenM2MAction', function () {
    beforeEach(function () {
        Cache::flush();

        Config::set('app.name', 'test-app');
        Config::set('app.env', 'testing');
        Config::set('gatekeeper.users_api_url', 'https://users-api.test');
        Config::set('services.users.m2m.client_id', 'test-client-id');
        Config::set('services.users.m2m.client_secret', 'test-client-secret');
        Config::set('services.users.m2m.ttl', 60);
    });

    describe('execute', function () {
        it('retrieves m2m token successfully from oauth endpoint', function () {
            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn('test-token-123');

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')
                ->once()
                ->with('oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'test-client-id',
                    'client_secret' => 'test-client-secret',
                    'scope' => '*',
                ])
                ->andReturn($response);

            $token = GetTokenM2MAction::execute();

            expect($token)->toBe('test-token-123');
        });

        it('sends correct client credentials grant request to oauth endpoint', function () {
            Config::set('services.users.m2m.client_id', 'test-client-id-custom');
            Config::set('services.users.m2m.client_secret', 'test-client-secret-custom');

            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn('test-token');

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')
                ->once()
                ->with('oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'test-client-id-custom',
                    'client_secret' => 'test-client-secret-custom',
                    'scope' => '*',
                ])
                ->andReturn($response);

            GetTokenM2MAction::execute();
        });

        it('returns null when http request returns unsuccessful status', function () {
            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn(null);

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->once()->andReturn($response);

            $token = GetTokenM2MAction::execute();

            expect($token)->toBeNull();
        });

        it('caches token to avoid repeated http requests', function () {
            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn('cached-token');

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->once()->andReturn($response);

            $firstToken = GetTokenM2MAction::execute();
            $secondToken = GetTokenM2MAction::execute();

            expect($firstToken)->toBe('cached-token')->and($secondToken)->toBe('cached-token');
        });

        it('uses app name and environment in cache key', function () {
            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn('token');

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->once()->andReturn($response);

            Config::set('app.name', 'marketplace-api');
            Config::set('app.env', 'production');

            GetTokenM2MAction::execute();

            $cachedValue = Cache::get('marketplace-api.production.m2m.token');

            expect($cachedValue)->toBe('token');
        });

        it('returns null when request fails with server error', function () {
            $response = Mockery::mock();
            $response->shouldReceive('json')->with('access_token')->andReturn(null);

            Http::shouldReceive('baseUrl')->once()->with('https://users-api.test')->andReturnSelf();
            Http::shouldReceive('post')->once()->andReturn($response);

            $token = GetTokenM2MAction::execute();

            expect($token)->toBeNull();
        });
    });
});
