<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Laravel\Socialite\SocialiteServiceProvider;
use MobileStock\Gatekeeper\Providers\GatekeeperServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        Config::set('services.users', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'redirect-url',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [GatekeeperServiceProvider::class, SocialiteServiceProvider::class];
    }
}
