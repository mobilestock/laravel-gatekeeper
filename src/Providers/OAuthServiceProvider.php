<?php

namespace MobileStock\OAuth2Helper\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;
use MobileStock\OAuth2Helper\Socialite\UsersProvider;
use MobileStock\OAuth2Helper\TokenGuard;

class OAuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->registerTokenUsersGuard();

        $socialite = $this->app->make(Factory::class);

        $socialite->extend('users', function () use ($socialite) {
            $config = Config::get('services.users');

            return $socialite->buildProvider(UsersProvider::class, $config);
        });
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
