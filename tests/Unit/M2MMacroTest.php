<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

describe('M2M Macro', function () {
    beforeEach(function () {
        Cache::flush();

        Config::set('app.name', 'test-app');
        Config::set('app.env', 'testing');
        Config::set('gatekeeper.users_api_url', 'https://users-api.test');
        Config::set('services.m2m.client_id', 'test-client-id');
        Config::set('services.m2m.client_secret', 'test-client-secret');
    });

    describe('withM2M macro on PendingRequest', function () {
        it('macro is registered on PendingRequest', function () {
            expect(PendingRequest::hasMacro('withM2M'))->toBeTrue();
        });

        it('applies m2m token to request authorization header', function () {
            Http::fake(function ($request) {
                if ($request->url() === 'https://users-api.test/oauth/token') {
                    return Http::response(['access_token' => 'bearer-token-xyz']);
                }
                return Http::response(['success' => true]);
            });

            $response = Http::baseUrl('https://api.example.com')
                ->withM2M()
                ->get('/protected-endpoint');

            expect($response->successful())->toBeTrue();
        });

        it('reuses cached token for multiple requests', function () {
            Http::fake(function ($request) {
                if ($request->url() === 'https://users-api.test/oauth/token') {
                    return Http::response(['access_token' => 'cached-bearer-token']);
                }
                return Http::response(['success' => true]);
            });

            Http::baseUrl('https://api.example.com')->withM2M()->get('/endpoint1');
            Http::baseUrl('https://api.example.com')->withM2M()->get('/endpoint2');

            Http::assertSent(function ($request) {
                return $request->url() === 'https://users-api.test/oauth/token';
            });
        });

        it('works with post requests', function () {
            Http::fake(function ($request) {
                if ($request->url() === 'https://users-api.test/oauth/token') {
                    return Http::response(['access_token' => 'token123']);
                }
                return Http::response(['id' => 1]);
            });

            $response = Http::baseUrl('https://api.example.com')
                ->withM2M()
                ->post('/resource', ['name' => 'test']);

            expect($response->successful())->toBeTrue();
        });

        it('works with put requests', function () {
            Http::fake(function ($request) {
                if ($request->url() === 'https://users-api.test/oauth/token') {
                    return Http::response(['access_token' => 'token123']);
                }
                return Http::response(['id' => 1]);
            });

            $response = Http::baseUrl('https://api.example.com')
                ->withM2M()
                ->put('/resource/1', ['name' => 'updated']);

            expect($response->successful())->toBeTrue();
        });

        it('works with delete requests', function () {
            Http::fake(function ($request) {
                if ($request->url() === 'https://users-api.test/oauth/token') {
                    return Http::response(['access_token' => 'token123']);
                }
                return Http::response();
            });

            $response = Http::baseUrl('https://api.example.com')
                ->withM2M()
                ->delete('/resource/1');

            expect($response->successful())->toBeTrue();
        });
    });
});
