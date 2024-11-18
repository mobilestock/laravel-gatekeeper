<?php

namespace MobileStock\Gatekeeper\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Socialite\UsersProvider;
use MobileStock\Gatekeeper\TokenGuard;

class GatekeeperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->registerTokenUsersGuard();

        Socialite::extend('users', function () {
            $config = Config::get('services.users');

            return Socialite::buildProvider(UsersProvider::class, $config);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/services.php', 'services');
    }

    protected function registerTokenUsersGuard(): void
    {
        Auth::extend('token_users', function ($app, $name, array $config) {
            if (isset($config['provider'])) {
                $provider = Auth::createUserProvider($config['provider']);
            }

            return new TokenGuard($provider ?? null, Request::instance());
        });
    }
}