<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Laravel\Socialite\SocialiteServiceProvider;
use MobileStock\OAuth2Helper\Providers\OAuthServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        Config::set('services.users', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'redirect-url',
            'frontend' => 'https://frontend-url.com',
            'backend' => 'https://backend-url.com/',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [OAuthServiceProvider::class, SocialiteServiceProvider::class];
    }
}
