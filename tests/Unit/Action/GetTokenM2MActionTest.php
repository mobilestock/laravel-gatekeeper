<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use MobileStock\Gatekeeper\Action\GetTokenM2MAction;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

describe('GetTokenM2MAction', function () {
    beforeEach(function () {
        Cache::flush();

        Config::set('app.name', 'test-app');
        Config::set('app.env', 'testing');
        Config::set('gatekeeper.users_api_url', 'https://users-api.test');
        Config::set('services.m2m.client_id', 'test-client-id');
        Config::set('services.m2m.client_secret', 'test-client-secret');
        Config::set('services.m2m.ttl', 60);
    });

    describe('execute', function () {
        it('retrieves m2m token successfully from oauth endpoint', function () {
            Http::fake(function ($request) {
                return Http::response(['access_token' => 'test-token-123']);
            });

            $token = GetTokenM2MAction::execute();

            expect($token)->toBe('test-token-123');
        });

        it('sends correct client credentials grant request to oauth endpoint', function () {
            Http::fake(function ($request) {
                return Http::response(['access_token' => 'test-token']);
            });

            GetTokenM2MAction::execute();

            Http::assertSent(function ($request) {
                return $request->url() === 'https://users-api.test/oauth/token'
                    && $request->method() === 'POST'
                    && $request['grant_type'] === 'client_credentials'
                    && $request['scope'] === '*';
            });
        });

        it('throws exception when http request returns unsuccessful status', function () {
            Http::fake(function ($request) {
                return Http::response([], 401);
            });

            expect(fn() => GetTokenM2MAction::execute())
                ->toThrow(BadRequestHttpException::class);
        });

        it('caches token to avoid repeated http requests', function () {
            Http::fake(function ($request) {
                return Http::response(['access_token' => 'cached-token']);
            });

            $firstToken = GetTokenM2MAction::execute();
            $secondToken = GetTokenM2MAction::execute();

            expect($firstToken)->toBe('cached-token')
                ->and($secondToken)->toBe('cached-token');

            Http::assertSentCount(1);
        });

        it('uses app name and environment in cache key', function () {
            Http::fake(function ($request) {
                return Http::response(['access_token' => 'token']);
            });

            Config::set('app.name', 'marketplace-api');
            Config::set('app.env', 'production');

            GetTokenM2MAction::execute();

            $cachedValue = Cache::get('marketplace-api.production.m2m.token');

            expect($cachedValue)->toBe('token');
        });

        it('throws exception with descriptive message when request fails', function () {
            Http::fake(function ($request) {
                return Http::response([], 500);
            });

            expect(fn() => GetTokenM2MAction::execute())
                ->toThrow(
                    BadRequestHttpException::class,
                    'Failed to retrieve M2M token'
                );
        });
    });
});
